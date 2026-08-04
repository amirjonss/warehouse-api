<?php

namespace App\Component\Receipt;

use App\Component\Product\Enums\Currency;
use App\Entity\Receipt;

class ReceiptTotalsCalculator
{
    public function recalculate(Receipt $receipt): void
    {
        $totalUsd = '0';
        $totalUzs = '0';

        foreach ($receipt->getItems() as $receiptItem) {
            if ($receiptItem->getCurrency() === Currency::USD) {
                $totalUsd = bcadd($totalUsd, $receiptItem->getTotal(), 2);
            } elseif ($receiptItem->getCurrency() === Currency::UZS) {
                $totalUzs = bcadd($totalUzs, $receiptItem->getTotal(), 2);
            }
        }

        $receipt
            ->setTotalUsd($totalUsd)
            ->setTotalUzs($totalUzs);
    }
}
