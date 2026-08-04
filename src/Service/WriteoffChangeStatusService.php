<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Writeoff\Exceptions\InsufficientBatchQuantityException;
use App\Component\Writeoff\Exceptions\WriteoffStatusTransitionException;
use App\Entity\Writeoff;
use App\Repository\BatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class WriteoffChangeStatusService
{
    public function __construct(
        private BatchRepository $batchRepository,
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

        if ($newStatus === DocStatus::POSTED) {
            if (count($writeoff->getItems()) === 0) {
                throw new WriteoffStatusTransitionException('Cannot post a writeoff without items.');
            }

            $this->assertHasEnoughQuantity($writeoff);
        }

        $this->entityManager->flush();

        return $writeoff;
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
            $remainingQty = $this->batchRepository->getRemainingQty($batch);

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

    private function getPreviousStatus(Writeoff $writeoff): ?DocStatus
    {
        return $this->entityManager->getUnitOfWork()->getOriginalEntityData($writeoff)['status'] ?? null;
    }
}
