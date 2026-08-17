<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class TopProductsDto
{
    /**
     * @param TopProductItemDto[] $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }
}
