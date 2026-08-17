<?php

declare(strict_types=1);

namespace App\Component\Client\Dtos;

class ClientDebtAgingItemDto
{
    public function __construct(
        public readonly int $clientId,
        public readonly string $oldestDebtDate,
    ) {
    }
}
