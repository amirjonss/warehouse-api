<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class TopProductItemDto
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $quantity,
        public readonly string $totalUsd,
        public readonly string $totalUzs,
    ) {
    }
}
