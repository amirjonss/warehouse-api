<?php

namespace App\Component\Client\Dtos;

class ClientDebtAgingDto
{
    /**
     * @param ClientDebtAgingItemDto[] $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }
}
