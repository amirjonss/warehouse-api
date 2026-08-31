<?php

declare(strict_types=1);

namespace App\Component\Core;

use App\Component\Product\Enums\Currency;

/**
 * How much debt a given sum of money closes.
 *
 * The money is counted in the document's currency, while the debt being closed may be
 * in the other one — the supplier agrees to accept sums for a dollar invoice, or the
 * other way round. The rate is the one the parties agreed on, not a reference rate.
 */
class AmountClosedCalculator
{
    public function calculate(
        Currency $allocationCurrency,
        Currency $documentCurrency,
        string $amountSpent,
        ?string $payRate
    ): string {
        if ($allocationCurrency === $documentCurrency) {
            return $amountSpent;
        }

        if ($allocationCurrency === Currency::USD) {
            return bcdiv($amountSpent, $payRate, 2);
        }

        return bcmul($amountSpent, $payRate, 2);
    }
}
