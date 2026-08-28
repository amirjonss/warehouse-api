<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class CashSessionSummaryDto
{
    /**
     * @param CashTurnoverItemDto[] $turnover
     */
    public function __construct(
        #[Groups('cash-summary:read')]
        public readonly string $balanceUsd,
        #[Groups('cash-summary:read')]
        public readonly string $balanceUzs,
        #[Groups('cash-summary:read')]
        public readonly string $unconfirmedUsd,
        #[Groups('cash-summary:read')]
        public readonly string $unconfirmedUzs,
        #[Groups('cash-summary:read')]
        public readonly array $turnover,
    ) {
    }
}
