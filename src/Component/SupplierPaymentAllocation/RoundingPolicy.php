<?php

declare(strict_types=1);

namespace App\Component\SupplierPaymentAllocation;

use App\Component\Product\Enums\Currency;

/**
 * Cross-currency allocation truncates: 12 000 000 sums at an agreed 12 345 closes
 * 972.05 USD, leaving 0.0018 hanging on the receipt forever — a payable no whole-kopeck
 * payment can ever close.
 *
 * When the gap is that small it is an artefact of the division, not money, and it is
 * written off so the receipt lands on exactly zero. A larger gap is real money and stays
 * outstanding.
 */
class RoundingPolicy
{
    /** A kopeck of USD is worth around 125 sums, so the grains differ by two orders. */
    public const TOLERANCE_USD = '0.01';
    public const TOLERANCE_UZS = '1.00';

    public function tolerance(Currency $currency): string
    {
        return $currency === Currency::USD ? self::TOLERANCE_USD : self::TOLERANCE_UZS;
    }

    /**
     * @return string signed write-off; '0.00' when the gap is real money rather than a
     *                rounding artefact
     */
    public function resolve(string $outstanding, string $amountClosed, Currency $currency): string
    {
        $gap = bcsub($outstanding, $amountClosed, 2);

        if (bccomp($gap, '0', 2) === 0) {
            return '0.00';
        }

        $magnitude = bccomp($gap, '0', 2) < 0 ? bcmul($gap, '-1', 2) : $gap;

        return bccomp($magnitude, $this->tolerance($currency), 2) <= 0 ? $gap : '0.00';
    }
}
