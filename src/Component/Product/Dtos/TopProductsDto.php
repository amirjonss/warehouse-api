<?php

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
