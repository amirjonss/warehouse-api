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
use App\Entity\Batch;
use App\Entity\Inventory;
use App\Entity\Product;
use App\Repository\BatchRepository;
use App\Repository\InventoryRepository;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class InventoryChangeStatusService
{
    public function __construct(
        private BatchRepository $batchRepository,
        private ProductRepository $productRepository,
        private InventoryRepository $inventoryRepository,
        private StockMovementRepository $stockMovementRepository,
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

            // Planned in full before a single movement is written: StockMovementFactory mutates
            // the cached remainders as it goes, so planning against half-applied state would
            // hand the later lines the wrong picture of the queue.
            $plan = $this->planAdjustments($inventory);
            $this->recordMovements($inventory, $plan);

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

            $plan = $this->planReversal($inventory);
            $this->recordMovements($inventory, $plan);

            return $inventory;
        });
    }

    /**
     * Null means "not counted yet", and posting such a line would write the whole product off as
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
     * Turns each line's difference into concrete per-batch movements.
     *
     * The discrepancy is measured against the snapshot taken when the line was created, not
     * against the ledger as it stands now: the count is a statement about that moment, and the
     * sales or receipts that landed during it are already recorded and must survive posting.
     *
     * Which batch absorbs it is a separate question, and the answer is the front of the FIFO
     * queue in both directions — the layer the warehouse is actually selling from. A shortage
     * is taken from the oldest batch with stock and spills into the next when that runs out; a
     * surplus goes back onto that same oldest batch, so found goods rejoin the queue where they
     * left it and carry the cost they were bought at.
     *
     * @return array<int, array{batch: Batch, product: Product, quantity: string}>
     */
    private function planAdjustments(Inventory $inventory): array
    {
        $plan = [];

        foreach ($inventory->getItems() as $inventoryItem) {
            $diff = $inventoryItem->getDiffQty() ?? '0';
            if (bccomp($diff, '0', 3) === 0) {
                continue;
            }

            $product = $inventoryItem->getProduct();
            $queue = $this->batchRepository->findFifoQueue($product);

            if ($queue === []) {
                throw new NegativeBatchRemainderException(sprintf(
                    'Товар «%s» ни разу не приходовался — записать расхождение не на что. '
                    . 'Сначала оприходуйте его приходом.',
                    $product->getName()
                ));
            }

            $plan = array_merge(
                $plan,
                bccomp($diff, '0', 3) < 0
                    ? $this->planShortage($product, $queue, bcmul($diff, '-1', 3))
                    : [$this->planSurplus($product, $queue, $diff)]
            );
        }

        return $plan;
    }

    /**
     * FIFO from the front: the goods that went missing are the ones that were due to leave next.
     *
     * @param array<int, array{batch: Batch, remainingQty: string}> $queue
     *
     * @return array<int, array{batch: Batch, product: Product, quantity: string}>
     */
    private function planShortage(Product $product, array $queue, string $missing): array
    {
        $plan = [];

        foreach ($queue as $entry) {
            if (bccomp($missing, '0', 3) <= 0) {
                break;
            }
            if (bccomp($entry['remainingQty'], '0', 3) <= 0) {
                continue;
            }

            $take = bccomp($entry['remainingQty'], $missing, 3) < 0 ? $entry['remainingQty'] : $missing;
            $plan[] = [
                'batch' => $entry['batch'],
                'product' => $product,
                'quantity' => bcmul($take, '-1', 3),
            ];
            $missing = bcsub($missing, $take, 3);
        }

        if (bccomp($missing, '0', 3) > 0) {
            throw new NegativeBatchRemainderException(sprintf(
                'По товару «%s» не хватает %s, чтобы списать недостачу — на партиях меньше, '
                . 'чем показывает пересчёт. Проверьте, не прошли ли движения после снимка.',
                $product->getName(),
                $missing
            ));
        }

        return $plan;
    }

    /**
     * All of it onto the oldest batch that still has stock — the one being sold from, so the
     * found goods go out next at the cost they came in at. If every batch is exhausted the
     * newest one takes them: it carries the only cost we still have any reason to believe.
     *
     * @param array<int, array{batch: Batch, remainingQty: string}> $queue
     *
     * @return array{batch: Batch, product: Product, quantity: string}
     */
    private function planSurplus(Product $product, array $queue, string $found): array
    {
        $target = null;
        foreach ($queue as $entry) {
            if (bccomp($entry['remainingQty'], '0', 3) > 0) {
                $target = $entry['batch'];
                break;
            }
        }
        $target ??= end($queue)['batch'];

        return ['batch' => $target, 'product' => $product, 'quantity' => $found];
    }

    /**
     * Cancelling mirrors the rows the posting actually wrote instead of re-deriving the split.
     * The journal already knows which batches absorbed what; recomputing it now would land on
     * different batches, because the stock has moved on since.
     *
     * @return array<int, array{batch: Batch, product: Product, quantity: string}>
     */
    private function planReversal(Inventory $inventory): array
    {
        $movements = $this->stockMovementRepository->findByDocument(
            DocumentType::INVENTORY,
            $inventory->getId()
        );

        $plan = [];
        foreach ($movements as $movement) {
            $mirror = bcmul($movement->getQuantity(), '-1', 3);
            $batch = $movement->getBatch();

            // Reversing a surplus takes goods back out, and they may already be sold.
            if (bccomp($mirror, '0', 3) < 0) {
                $remainingQty = $this->batchRepository->computeLiveRemainingQty($batch);
                if (bccomp(bcadd($remainingQty, $mirror, 3), '0', 3) < 0) {
                    throw new NegativeBatchRemainderException(sprintf(
                        'Отмена невозможна: после проведения по партии «%s» прошли движения, '
                        . 'остаток стал бы отрицательным. Проведите новую инвентаризацию.',
                        $batch->getNumber()
                    ));
                }
            }

            $plan[] = ['batch' => $batch, 'product' => $movement->getProduct(), 'quantity' => $mirror];
        }

        return $plan;
    }

    /**
     * @param array<int, array{batch: Batch, product: Product, quantity: string}> $plan
     */
    private function recordMovements(Inventory $inventory, array $plan): void
    {
        if ($plan === []) {
            return;
        }

        $this->batchRepository->lockBatches(array_column($plan, 'batch'));

        foreach ($plan as $entry) {
            $stockMovement = $this->stockMovementFactory->create(
                MovementType::ADJUST,
                $entry['product'],
                $entry['batch'],
                $entry['quantity'],
                DocumentType::INVENTORY,
                $inventory->getId(),
                $inventory->getNumber(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($stockMovement);
        }
    }

    /**
     * @return Product[]
     */
    private function collectProducts(Inventory $inventory): array
    {
        $products = [];
        foreach ($inventory->getItems() as $inventoryItem) {
            $products[] = $inventoryItem->getProduct();
        }

        return $products;
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
