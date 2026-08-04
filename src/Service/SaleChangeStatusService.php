<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Component\Sale\Exceptions\InsufficientBatchQuantityException;
use App\Component\Sale\Exceptions\SaleStatusTransitionException;
use App\Component\StockMovement\StockMovementFactory;
use App\Component\User\CurrentUser;
use App\Entity\Sale;
use App\Repository\BatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class SaleChangeStatusService
{
    public function __construct(
        private BatchRepository $batchRepository,
        private StockMovementFactory $stockMovementFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Sale $sale): Sale
    {
        $previousStatus = $this->getPreviousStatus($sale);
        $newStatus = $sale->getStatus();

        if ($previousStatus === $newStatus) {
            return $sale;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new SaleStatusTransitionException('A posted sale cannot be moved back to draft.');
        }

        if ($newStatus === DocStatus::POSTED) {
            if (count($sale->getItems()) === 0) {
                throw new SaleStatusTransitionException('Cannot post a sale without items.');
            }

            $this->assertHasEnoughQuantity($sale);
            $this->recordOutMovements($sale);
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            $this->reverseMovements($sale);
        }

        $this->entityManager->flush();

        return $sale;
    }

    private function assertHasEnoughQuantity(Sale $sale): void
    {
        $requestedQtyByBatch = [];
        $batchesById = [];

        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $batch = $allocation->getBatch();
                $batchesById[$batch->getId()] = $batch;
                $requestedQtyByBatch[$batch->getId()] = bcadd(
                    $requestedQtyByBatch[$batch->getId()] ?? '0',
                    $allocation->getQuantity(),
                    3
                );
            }
        }

        foreach ($requestedQtyByBatch as $batchId => $requestedQty) {
            $batch = $batchesById[$batchId];
            $remainingQty = $this->batchRepository->getRemainingQty($batch);

            if (bccomp($requestedQty, $remainingQty, 3) > 0) {
                throw new InsufficientBatchQuantityException(sprintf(
                    'Cannot sell %s of batch "%s": only %s left.',
                    $requestedQty,
                    $batch->getNumber(),
                    $remainingQty
                ));
            }
        }
    }

    private function recordOutMovements(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::OUT,
                    $saleItem->getProduct(),
                    $allocation->getBatch(),
                    bcmul($allocation->getQuantity(), '-1', 3),
                    DocumentType::SALE,
                    $sale->getId(),
                    $sale->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);
            }
        }
    }

    private function reverseMovements(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::ADJUST,
                    $saleItem->getProduct(),
                    $allocation->getBatch(),
                    $allocation->getQuantity(),
                    DocumentType::SALE,
                    $sale->getId(),
                    $sale->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);
            }
        }
    }

    private function getPreviousStatus(Sale $sale): ?DocStatus
    {
        return $this->entityManager->getUnitOfWork()->getOriginalEntityData($sale)['status'] ?? null;
    }
}
