<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Receipt\ReceiptTotalsCalculator;
use App\Component\ReceiptItem\Exceptions\ReceiptNotEditableException;
use App\Entity\ReceiptItem;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptItemDeleteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptTotalsCalculator $receiptTotalsCalculator,
    ) {
    }

    public function delete(ReceiptItem $receiptItem): void
    {
        $receipt = $receiptItem->getReceipt();

        if ($receipt->getStatus() !== DocStatus::DRAFT) {
            throw new ReceiptNotEditableException('Cannot modify items of a receipt that is not in draft status.');
        }

        $this->entityManager->wrapInTransaction(function () use ($receipt, $receiptItem) {
            $this->entityManager->remove($receiptItem);
            $receipt->removeItem($receiptItem);
            $this->receiptTotalsCalculator->recalculate($receipt);
        });
    }
}
