<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Batch\BatchFactory;
use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Component\Receipt\Exceptions\ReceiptStatusTransitionException;
use App\Component\StockMovement\StockMovementFactory;
use App\Component\User\CurrentUser;
use App\Entity\Receipt;
use App\Repository\BatchRepository;
use App\Repository\ProductRepository;
use App\Repository\ReceiptRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptChangeStatusService
{
    public function __construct(
        private BatchFactory $batchFactory,
        private BatchRepository $batchRepository,
        private ProductRepository $productRepository,
        private ReceiptRepository $receiptRepository,
        private StockMovementFactory $stockMovementFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Receipt $receipt): Receipt
    {
        $previousStatus = $this->getPreviousStatus($receipt);
        $newStatus = $receipt->getStatus();

        if ($previousStatus === $newStatus) {
            return $receipt;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new ReceiptStatusTransitionException('A posted receipt cannot be moved back to draft.');
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($receipt, $previousStatus);
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($receipt, $previousStatus);
        }

        $this->entityManager->flush();

        return $receipt;
    }

    private function post(Receipt $receipt, ?DocStatus $previousStatus): Receipt
    {
        if (count($receipt->getItems()) === 0) {
            throw new ReceiptStatusTransitionException('Cannot post a receipt without items.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($receipt, $previousStatus) {
            $this->receiptRepository->lockReceipts([$receipt]);
            $this->assertNotChangedConcurrently($receipt, $previousStatus);

            $this->productRepository->lockProducts($this->collectProducts($receipt));

            foreach ($receipt->getItems() as $receiptItem) {
                if ($receiptItem->getBatch() !== null) {
                    continue;
                }

                $batch = $this->batchFactory->create($receiptItem);
                $this->entityManager->persist($batch);
                $receiptItem->setBatch($batch);

                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::IN,
                    $receiptItem->getProduct(),
                    $batch,
                    $receiptItem->getQuantity(),
                    DocumentType::RECEIPT,
                    $receipt->getId(),
                    $receipt->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);
            }

            return $receipt;
        });
    }

    private function collectProducts(Receipt $receipt): array
    {
        $products = [];
        foreach ($receipt->getItems() as $receiptItem) {
            $products[] = $receiptItem->getProduct();
        }

        return $products;
    }

    private function cancel(Receipt $receipt, ?DocStatus $previousStatus): Receipt
    {
        return $this->entityManager->wrapInTransaction(function () use ($receipt, $previousStatus) {
            $this->receiptRepository->lockReceipts([$receipt]);
            $this->assertNotChangedConcurrently($receipt, $previousStatus);

            $this->batchRepository->lockBatches($this->collectBatches($receipt));

            foreach ($receipt->getItems() as $receiptItem) {
                $batch = $receiptItem->getBatch();
                if ($batch !== null && $this->batchRepository->isUsed($batch)) {
                    throw new ReceiptStatusTransitionException(sprintf(
                        'Batch "%s" is already used and the receipt cannot be cancelled.',
                        $batch->getNumber()
                    ));
                }
            }

            foreach ($receipt->getItems() as $receiptItem) {
                $batch = $receiptItem->getBatch();
                if ($batch === null) {
                    continue;
                }

                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::ADJUST,
                    $receiptItem->getProduct(),
                    $batch,
                    bcmul($batch->getInitialQty(), '-1', 3),
                    DocumentType::RECEIPT,
                    $receipt->getId(),
                    $receipt->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);

                $receiptItem->setBatch(null);
            }

            return $receipt;
        });
    }

    private function collectBatches(Receipt $receipt): array
    {
        $batches = [];
        foreach ($receipt->getItems() as $receiptItem) {
            if ($receiptItem->getBatch() !== null) {
                $batches[] = $receiptItem->getBatch();
            }
        }

        return $batches;
    }

    private function assertNotChangedConcurrently(Receipt $receipt, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null && $this->receiptRepository->getCurrentStatus($receipt->getId()) !== $expectedStatus->value) {
            throw new ReceiptStatusTransitionException(
                'This receipt was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function getPreviousStatus(Receipt $receipt): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($receipt)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
