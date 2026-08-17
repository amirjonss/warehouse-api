<?php

declare(strict_types=1);

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
