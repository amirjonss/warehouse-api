<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Receipt\ReceiptTotalsCalculator;
use App\Component\ReceiptItem\Exceptions\ReceiptNotEditableException;
use App\Entity\ReceiptItem;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptItemUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptTotalsCalculator $receiptTotalsCalculator,
    ) {
    }

    public function update(ReceiptItem $receiptItem): ReceiptItem
    {
        if ($receiptItem->getReceipt()->getStatus() !== DocStatus::DRAFT) {
            throw new ReceiptNotEditableException('Cannot modify items of a receipt that is not in draft status.');
        }

        $receiptItem->setTotal(bcmul($receiptItem->getPrice(), $receiptItem->getQuantity(), 2));

        $this->receiptTotalsCalculator->recalculate($receiptItem->getReceipt());

        $this->entityManager->flush();

        return $receiptItem;
    }
}
