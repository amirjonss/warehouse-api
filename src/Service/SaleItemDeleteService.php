<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Sale\SaleTotalsCalculator;
use App\Component\SaleItem\Exceptions\SaleNotEditableException;
use App\Entity\SaleItem;
use Doctrine\ORM\EntityManagerInterface;

class SaleItemDeleteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleTotalsCalculator $saleTotalsCalculator,
    ) {
    }

    public function delete(SaleItem $saleItem): void
    {
        $sale = $saleItem->getSale();

        if ($sale->getStatus() !== DocStatus::DRAFT) {
            throw new SaleNotEditableException('Cannot modify items of a sale that is not in draft status.');
        }

        $this->entityManager->wrapInTransaction(function () use ($sale, $saleItem) {
            $this->entityManager->remove($saleItem);
            $sale->removeItem($saleItem);
            $this->saleTotalsCalculator->recalculate($sale);
        });
    }
}
