<?php

namespace App\Component\ReceiptItem;

use App\Component\Receipt\ReceiptTotalsCalculator;
use App\Entity\ReceiptItem;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptItemFactory
{
    public function __construct(
        private readonly ReceiptTotalsCalculator $receiptTotalsCalculator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(ReceiptItem $data): ReceiptItem
    {
        $receiptItem = new ReceiptItem();
        $receiptItem
            ->setReceipt($data->getReceipt())
            ->setCurrency($data->getCurrency())
            ->setPrice($data->getPrice())
            ->setQuantity($data->getQuantity())
            ->setRate($data->getRate())
            ->setTotal(bcmul($data->getPrice(), $data->getQuantity(), 2))
            ->setProduct($data->getProduct());

        $data->getReceipt()->addItem($receiptItem);
        $this->receiptTotalsCalculator->recalculate($data->getReceipt());
        $this->entityManager->persist($receiptItem);
        $this->entityManager->flush();

        return $receiptItem;
    }
}
