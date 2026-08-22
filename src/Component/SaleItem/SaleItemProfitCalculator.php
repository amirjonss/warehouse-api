<?php

declare(strict_types=1);

namespace App\Component\SaleItem;

use App\Component\Product\Enums\Currency;
use App\Component\SaleItem\Exceptions\MissingSaleRateException;
use App\Entity\SaleItemAllocation;

class SaleItemProfitCalculator
{
    public function calculate(SaleItemAllocation $allocation): string
    {
        $revenue = bcmul($allocation->getQuantity(), $allocation->getSaleItem()->getPrice(), 2);
        $revenueInCostCurrency = $this->convertToCostCurrency($revenue, $allocation);

        $cost = bcmul($allocation->getQuantity(), $allocation->getCostPrice(), 2);

        return bcsub($revenueInCostCurrency, $cost, 2);
    }

    private function convertToCostCurrency(string $revenue, SaleItemAllocation $allocation): string
    {
        $saleItem = $allocation->getSaleItem();

        if ($saleItem->getCurrency() === $allocation->getCostCurrency()) {
            return $revenue;
        }

        if ($saleItem->getRate() === null) {
            throw new MissingSaleRateException(sprintf(
                'Товар «%s» продан в валюте %s, а закуплен в %s — укажите курс в позиции продажи.',
                $saleItem->getProduct()->getName(),
                $saleItem->getCurrency()->value,
                $allocation->getCostCurrency()->value
            ));
        }

        if ($allocation->getCostCurrency() === Currency::USD) {
            return bcdiv($revenue, $saleItem->getRate(), 2);
        }

        return bcmul($revenue, $saleItem->getRate(), 2);
    }
}
