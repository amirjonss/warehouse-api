<?php

declare(strict_types=1);

namespace App\Component\Sale\Dtos;

class SalesAnalysisRowDto
{
    public function __construct(
        /** Start of the interval, YYYY-MM-DD. Null on the grand total row. */
        public readonly ?string $period,
        public readonly int $documents,
        public readonly string $quantity,
        public readonly string $revenue,
        /** Cost of the goods that left, at the price of the batch they came from. */
        public readonly string $cost,
        public readonly string $profit,
        /** Profit as a percent of revenue. */
        public readonly string $margin,
    ) {
    }
}
