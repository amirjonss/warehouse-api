<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class AbcItemDto
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly ?string $categoryName,
        public readonly ?string $unit,
        public readonly string $value,
        /** Percent of the whole this product accounts for. */
        public readonly string $share,
        /** Percent accumulated down to and including this product. */
        public readonly string $cumulativeShare,
        public readonly string $class,
    ) {
    }
}
