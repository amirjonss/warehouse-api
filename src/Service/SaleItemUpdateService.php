<?php

declare(strict_types=1);

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
        private SaleItemAllocationService $saleItemAllocationService,
    ) {
    }

    public function update(SaleItem $saleItem): SaleItem
    {
        if ($saleItem->getSale()->getStatus() !== DocStatus::DRAFT) {
            throw new SaleNotEditableException('Cannot modify items of a sale that is not in draft status.');
        }

        $saleItem->setTotal(bcmul($saleItem->getPrice(), $saleItem->getQuantity(), 2));

        return $this->entityManager->wrapInTransaction(function () use ($saleItem) {
            $this->saleItemAllocationService->allocate($saleItem);
            $this->saleTotalsCalculator->recalculate($saleItem->getSale());

            return $saleItem;
        });
    }
}
