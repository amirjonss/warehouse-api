<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class ProductStockSummaryDto
{
    public function __construct(
        public readonly int $positions,
        public readonly int $low,
        public readonly int $outOfStock,
    ) {
    }
}
