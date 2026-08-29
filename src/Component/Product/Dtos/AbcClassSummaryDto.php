<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class AbcClassSummaryDto
{
    public function __construct(
        public readonly string $class,
        public readonly int $products,
        public readonly string $value,
        public readonly string $share,
    ) {
    }
}
