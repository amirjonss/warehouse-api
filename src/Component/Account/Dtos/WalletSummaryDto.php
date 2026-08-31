<?php

declare(strict_types=1);

namespace App\Component\Account\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What the company holds right now. Money still in sellers' bags is not here — that is
 * what /cash_sessions/on_hands reports, and the two together are the whole picture.
 */
class WalletSummaryDto
{
    /**
     * @param WalletAccountDto[] $accounts
     */
    public function __construct(
        #[Groups('wallet-summary:read')]
        public readonly string $totalUsd,
        #[Groups('wallet-summary:read')]
        public readonly string $totalUzs,
        #[Groups('wallet-summary:read')]
        public readonly array $accounts,
    ) {
    }
}
