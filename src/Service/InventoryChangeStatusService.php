<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Component\Inventory\Exceptions\InventoryNotCountedException;
use App\Component\Inventory\Exceptions\InventoryStatusTransitionException;
use App\Component\Inventory\Exceptions\NegativeBatchRemainderException;
use App\Component\StockMovement\StockMovementFactory;
use App\Component\User\CurrentUser;
use App\Entity\Inventory;
use App\Entity\InventoryItem;
use App\Repository\BatchRepository;
use App\Repository\InventoryRepository;
use App\Repository\ProductRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class InventoryChangeStatusService
{
    public function __construct(
        private BatchRepository $batchRepository,
        private ProductRepository $productRepository,
        private InventoryRepository $inventoryRepository,
        private StockMovementFactory $stockMovementFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Inventory $inventory): Inventory
    {
        $previousStatus = $this->getPreviousStatus($inventory);
        $newStatus = $inventory->getStatus();

        if ($previousStatus === $newStatus) {
            return $inventory;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new InventoryStatusTransitionException(
                'Проведённую инвентаризацию нельзя вернуть в черновик.'
            );
        }

        if ($previousStatus === DocStatus::CANCELLED) {
            throw new InventoryStatusTransitionException(
                'Отменённую инвентаризацию нельзя провести заново — создайте новый документ.'
            );
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($inventory, $previousStatus);
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($inventory, $previousStatus);
        }

        $this->entityManager->flush();

        return $inventory;
    }

    private function post(Inventory $inventory, ?DocStatus $previousStatus): Inventory
    {
        if (count($inventory->getItems()) === 0) {
            throw new InventoryStatusTransitionException(
                'Нельзя провести инвентаризацию без строк.'
            );
        }

        $this->assertEveryLineCounted($inventory);

        return $this->entityManager->wrapInTransaction(function () use ($inventory, $previousStatus) {
            $this->inventoryRepository->lockInventories([$inventory]);
            $this->assertNotChangedConcurrently($inventory, $previousStatus);

            $this->productRepository->lockProducts($this->collectProducts($inventory));
            $this->batchRepository->lockBatches($this->collectBatches($inventory));

            $this->assertRemaindersStayNonNegative($inventory, '1');
            $this->recordAdjustments($inventory, '1');

            $inventory->setPostedAt(new DateTime());

            return $inventory;
        });
    }

    private function cancel(Inventory $inventory, ?DocStatus $previousStatus): Inventory
    {
        return $this->entityManager->wrapInTransaction(function () use ($inventory, $previousStatus) {
            $this->inventoryRepository->lockInventories([$inventory]);
            $this->assertNotChangedConcurrently($inventory, $previousStatus);

            $this->productRepository->lockProducts($this->collectProducts($inventory));
            $this->batchRepository->lockBatches($this->collectBatches($inventory));

            $this->assertRemaindersStayNonNegative($inventory, '-1');
            $this->recordAdjustments($inventory, '-1');

            return $inventory;
        });
    }

    /**
     * Null means "not counted yet", and posting such a line would write the whole batch off as
     * missing. A half-finished sheet has to be finished or trimmed, never assumed to be zero.
     */
    private function assertEveryLineCounted(Inventory $inventory): void
    {
        $uncounted = 0;
        foreach ($inventory->getItems() as $inventoryItem) {
            if ($inventoryItem->getActualQty() === null) {
                ++$uncounted;
            }
        }

        if ($uncounted > 0) {
            throw new InventoryNotCountedException(sprintf(
                'Не во всех строках указан факт: осталось %d. Заполните их или удалите.',
                $uncounted
            ));
        }
    }

    /**
     * The discrepancy is measured against the snapshot taken when the line was created, not
     * against the ledger as it stands now: the count is a statement about that moment, and the
     * sales or receipts that landed during it are already recorded and must survive posting.
     *
     * The sign flips on cancellation, which appends the mirror rows instead of deleting.
     */
    private function signedDiff(InventoryItem $inventoryItem, string $sign): string
    {
        return bcmul($inventoryItem->getDiffQty() ?? '0', $sign, 3);
    }

    /**
     * Checked for every line before a single movement is written: StockMovementFactory mutates
     * Batch.remainingQty as it goes, so validating while recording would compare against
     * half-applied state.
     *
     * A counted figure cannot be negative, but the delta is applied to the live remainder, not
     * to the snapshot — count 0 against a snapshot of 50 after 45 were sold would drive the
     * batch to -45. Reversing a surplus that has since been sold fails the same way.
     */
    private function assertRemaindersStayNonNegative(Inventory $inventory, string $sign): void
    {
        foreach ($inventory->getItems() as $inventoryItem) {
            $diff = $this->signedDiff($inventoryItem, $sign);
            if (bccomp($diff, '0', 3) >= 0) {
                continue;
            }

            $batch = $inventoryItem->getBatch();
            $remainingQty = $this->batchRepository->computeLiveRemainingQty($batch);

            if (bccomp(bcadd($remainingQty, $diff, 3), '0', 3) < 0) {
                throw new NegativeBatchRemainderException(sprintf(
                    'По партии «%s» остаток стал бы отрицательным: сейчас %s, поправка %s. '
                    . 'После проведения по партии прошли движения — проведите новую инвентаризацию.',
                    $batch->getNumber(),
                    $remainingQty,
                    $diff
                ));
            }
        }
    }

    /**
     * Lines where the count matched are kept — they are the evidence that the batch was
     * actually counted — but they move nothing, and a zero-quantity movement is rejected by
     * StockMovement's own assertion anyway.
     */
    private function recordAdjustments(Inventory $inventory, string $sign): void
    {
        foreach ($inventory->getItems() as $inventoryItem) {
            $diff = $this->signedDiff($inventoryItem, $sign);
            if (bccomp($diff, '0', 3) === 0) {
                continue;
            }

            $stockMovement = $this->stockMovementFactory->create(
                MovementType::ADJUST,
                $inventoryItem->getProduct(),
                $inventoryItem->getBatch(),
                $diff,
                DocumentType::INVENTORY,
                $inventory->getId(),
                $inventory->getNumber(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($stockMovement);
        }
    }

    /**
     * @return \App\Entity\Product[]
     */
    private function collectProducts(Inventory $inventory): array
    {
        $products = [];
        foreach ($inventory->getItems() as $inventoryItem) {
            $products[] = $inventoryItem->getProduct();
        }

        return $products;
    }

    /**
     * @return \App\Entity\Batch[]
     */
    private function collectBatches(Inventory $inventory): array
    {
        $batches = [];
        foreach ($inventory->getItems() as $inventoryItem) {
            $batches[] = $inventoryItem->getBatch();
        }

        return $batches;
    }

    private function assertNotChangedConcurrently(Inventory $inventory, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null
            && $this->inventoryRepository->getCurrentStatus($inventory->getId()) !== $expectedStatus->value
        ) {
            throw new InventoryStatusTransitionException(
                'Эту инвентаризацию уже изменил другой запрос. Обновите её и попробуйте снова.'
            );
        }
    }

    private function getPreviousStatus(Inventory $inventory): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($inventory)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
