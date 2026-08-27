<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Три величины смены плюс оборот по способам оплаты.
 *
 * balance* — что должно лежать в сумке; unconfirmed* — что отдано, но владельцем
 * не подтверждено; turnover — всё собранное, включая карту и перечисление,
 * которые обязательства не создают. Смешивать их в одно число нельзя.
 */
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
