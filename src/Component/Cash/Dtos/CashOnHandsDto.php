<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Сколько наличных сейчас у всех продавцов вместе — плитка на дашборде владельца.
 */
class CashOnHandsDto
{
    public function __construct(
        #[Groups('cash-on-hands:read')]
        public readonly string $balanceUsd,
        #[Groups('cash-on-hands:read')]
        public readonly string $balanceUzs,
        #[Groups('cash-on-hands:read')]
        public readonly string $unconfirmedUsd,
        #[Groups('cash-on-hands:read')]
        public readonly string $unconfirmedUzs,
        #[Groups('cash-on-hands:read')]
        public readonly int $openSessions,
    ) {
    }
}
