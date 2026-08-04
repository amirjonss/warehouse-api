<?php

namespace App\Service;

use App\Component\Batch\BatchFactory;
use App\Component\Core\Enums\DocStatus;
use App\Component\Receipt\Exceptions\ReceiptStatusTransitionException;
use App\Entity\Receipt;
use App\Repository\BatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptChangeStatusService
{
    public function __construct(
        private BatchFactory $batchFactory,
        private BatchRepository $batchRepository,
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
            return $this->cancel($receipt);
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($receipt);
        }

        $this->entityManager->flush();

        return $receipt;
    }

    private function post(Receipt $receipt): Receipt
    {
        if (count($receipt->getItems()) === 0) {
            throw new ReceiptStatusTransitionException('Cannot post a receipt without items.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($receipt) {
            foreach ($receipt->getItems() as $receiptItem) {
                if ($receiptItem->getBatch() !== null) {
                    continue;
                }

                $batch = $this->batchFactory->create($receiptItem);
                $this->entityManager->persist($batch);
                $receiptItem->setBatch($batch);
            }

            return $receipt;
        });
    }

    private function cancel(Receipt $receipt): Receipt
    {
        foreach ($receipt->getItems() as $receiptItem) {
            $batch = $receiptItem->getBatch();
            if ($batch !== null && $this->batchRepository->isUsed($batch)) {
                throw new ReceiptStatusTransitionException(sprintf(
                    'Batch "%s" is already used and the receipt cannot be cancelled.',
                    $batch->getNumber()
                ));
            }
        }

        return $this->entityManager->wrapInTransaction(function () use ($receipt) {
            foreach ($receipt->getItems() as $receiptItem) {
                $batch = $receiptItem->getBatch();
                if ($batch === null) {
                    continue;
                }

                $receiptItem->setBatch(null);
                $this->entityManager->remove($batch);
            }

            return $receipt;
        });
    }

    private function getPreviousStatus(Receipt $receipt): ?DocStatus
    {
        return $this->entityManager->getUnitOfWork()->getOriginalEntityData($receipt)['status'] ?? null;
    }
}
