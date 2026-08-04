<?php

namespace App\Component\SaleItem;

use App\Component\Sale\SaleTotalsCalculator;
use App\Entity\SaleItem;
use Doctrine\ORM\EntityManagerInterface;

class SaleItemFactory
{
    public function __construct(
        private readonly SaleTotalsCalculator $saleTotalsCalculator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(SaleItem $data): SaleItem
    {
        $batch = $data->getBatch();

        $saleItem = new SaleItem();
        $saleItem
            ->setSale($data->getSale())
            ->setProduct($data->getProduct())
            ->setBatch($batch)
            ->setQuantity($data->getQuantity())
            ->setPrice($data->getPrice())
            ->setCurrency($data->getCurrency())
            ->setTotal(bcmul($data->getPrice(), $data->getQuantity(), 2))
            ->setCostPrice($batch->getPurchasePrice())
            ->setCostCurrency($batch->getCurrency())
            ->setCostRate($batch->getRateSell());

        $data->getSale()->addItem($saleItem);
        $this->saleTotalsCalculator->recalculate($data->getSale());
        $this->entityManager->persist($saleItem);
        $this->entityManager->flush();

        return $saleItem;
    }
}
