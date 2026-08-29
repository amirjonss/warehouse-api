<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Component\StockMovement\StockMovementFactory;
use App\Component\User\CurrentUser;
use App\Component\Writeoff\Exceptions\InsufficientBatchQuantityException;
use App\Component\Writeoff\Exceptions\WriteoffStatusTransitionException;
use App\Entity\Writeoff;
use App\Repository\BatchRepository;
use App\Repository\WriteoffRepository;
use Doctrine\ORM\EntityManagerInterface;

class WriteoffChangeStatusService
{
    public function __construct(
        private BatchRepository $batchRepository,
        private WriteoffRepository $writeoffRepository,
        private StockMovementFactory $stockMovementFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Writeoff $writeoff): Writeoff
    {
        $previousStatus = $this->getPreviousStatus($writeoff);
        $newStatus = $writeoff->getStatus();

        if ($previousStatus === $newStatus) {
            return $writeoff;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new WriteoffStatusTransitionException('A posted writeoff cannot be moved back to draft.');
        }

        if ($previousStatus === DocStatus::CANCELLED) {
            throw new WriteoffStatusTransitionException(
                'Отменённое списание нельзя провести заново — создайте новый документ.'
            );
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($writeoff, $previousStatus);
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($writeoff, $previousStatus);
        }

        $this->entityManager->flush();

        return $writeoff;
    }

    private function post(Writeoff $writeoff, ?DocStatus $previousStatus): Writeoff
    {
        if (count($writeoff->getItems()) === 0) {
            throw new WriteoffStatusTransitionException('Cannot post a writeoff without items.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($writeoff, $previousStatus) {
            $this->writeoffRepository->lockWriteoffs([$writeoff]);
            $this->assertNotChangedConcurrently($writeoff, $previousStatus);

            $this->batchRepository->lockBatches($this->collectBatches($writeoff));

            $this->assertHasEnoughQuantity($writeoff);
            $this->recordWriteoffMovements($writeoff);

            return $writeoff;
        });
    }

    private function cancel(Writeoff $writeoff, ?DocStatus $previousStatus): Writeoff
    {
        return $this->entityManager->wrapInTransaction(function () use ($writeoff, $previousStatus) {
            $this->writeoffRepository->lockWriteoffs([$writeoff]);
            $this->assertNotChangedConcurrently($writeoff, $previousStatus);

            $this->reverseMovements($writeoff);

            return $writeoff;
        });
    }

    private function assertNotChangedConcurrently(Writeoff $writeoff, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null && $this->writeoffRepository->getCurrentStatus($writeoff->getId()) !== $expectedStatus->value) {
            throw new WriteoffStatusTransitionException(
                'This writeoff was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function collectBatches(Writeoff $writeoff): array
    {
        $batches = [];
        foreach ($writeoff->getItems() as $writeoffItem) {
            $batches[] = $writeoffItem->getBatch();
        }

        return $batches;
    }

    private function assertHasEnoughQuantity(Writeoff $writeoff): void
    {
        $requestedQtyByBatch = [];
        $batchesById = [];

        foreach ($writeoff->getItems() as $writeoffItem) {
            $batch = $writeoffItem->getBatch();
            $batchesById[$batch->getId()] = $batch;
            $requestedQtyByBatch[$batch->getId()] = bcadd(
                $requestedQtyByBatch[$batch->getId()] ?? '0',
                $writeoffItem->getQuantity(),
                3
            );
        }

        foreach ($requestedQtyByBatch as $batchId => $requestedQty) {
            $batch = $batchesById[$batchId];
            $remainingQty = $this->batchRepository->computeLiveRemainingQty($batch);

            if (bccomp($requestedQty, $remainingQty, 3) > 0) {
                throw new InsufficientBatchQuantityException(sprintf(
                    'Cannot write off %s of batch "%s": only %s left.',
                    $requestedQty,
                    $batch->getNumber(),
                    $remainingQty
                ));
            }
        }
    }

    private function recordWriteoffMovements(Writeoff $writeoff): void
    {
        foreach ($writeoff->getItems() as $writeoffItem) {
            $stockMovement = $this->stockMovementFactory->create(
                MovementType::WRITEOFF,
                $writeoffItem->getProduct(),
                $writeoffItem->getBatch(),
                bcmul($writeoffItem->getQuantity(), '-1', 3),
                DocumentType::WRITEOFF,
                $writeoff->getId(),
                $writeoff->getNumber(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($stockMovement);
        }
    }

    private function reverseMovements(Writeoff $writeoff): void
    {
        foreach ($writeoff->getItems() as $writeoffItem) {
            $stockMovement = $this->stockMovementFactory->create(
                MovementType::ADJUST,
                $writeoffItem->getProduct(),
                $writeoffItem->getBatch(),
                $writeoffItem->getQuantity(),
                DocumentType::WRITEOFF,
                $writeoff->getId(),
                $writeoff->getNumber(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($stockMovement);
        }
    }

    private function getPreviousStatus(Writeoff $writeoff): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($writeoff)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
