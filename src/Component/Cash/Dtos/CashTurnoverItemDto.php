<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class CashTurnoverItemDto
{
    public function __construct(
        #[Groups('cash-summary:read')]
        public readonly string $method,
        #[Groups('cash-summary:read')]
        public readonly string $currency,
        #[Groups('cash-summary:read')]
        public readonly string $total,
        #[Groups('cash-summary:read')]
        public readonly int $count,
    ) {
    }
}
