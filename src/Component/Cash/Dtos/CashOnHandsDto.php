<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * How much cash all sellers are holding right now: the tile on the owner's dashboard.
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
