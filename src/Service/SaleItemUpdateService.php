<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Sale\SaleTotalsCalculator;
use App\Component\SaleItem\Exceptions\SaleNotEditableException;
use App\Entity\SaleItem;
use Doctrine\ORM\EntityManagerInterface;

class SaleItemUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleTotalsCalculator $saleTotalsCalculator,
    ) {
    }

    public function update(SaleItem $saleItem): SaleItem
    {
        if ($saleItem->getSale()->getStatus() !== DocStatus::DRAFT) {
            throw new SaleNotEditableException('Cannot modify items of a sale that is not in draft status.');
        }

        $saleItem->setTotal(bcmul($saleItem->getPrice(), $saleItem->getQuantity(), 2));

        $this->saleTotalsCalculator->recalculate($saleItem->getSale());

        $this->entityManager->flush();

        return $saleItem;
    }
}
