<?php

namespace App\Component\Sale;

use App\Component\Product\Enums\Currency;
use App\Entity\Sale;

class SaleTotalsCalculator
{
    public function recalculate(Sale $sale): void
    {
        $totalUsd = '0';
        $totalUzs = '0';

        foreach ($sale->getItems() as $saleItem) {
            if ($saleItem->getCurrency() === Currency::USD) {
                $totalUsd = bcadd($totalUsd, $saleItem->getTotal(), 2);
            } elseif ($saleItem->getCurrency() === Currency::UZS) {
                $totalUzs = bcadd($totalUzs, $saleItem->getTotal(), 2);
            }
        }

        $sale
            ->setTotalUsd($totalUsd)
            ->setTotalUzs($totalUzs);
    }
}
