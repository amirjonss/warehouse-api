<?php

declare(strict_types=1);

namespace App\Component\Account\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class WalletAccountDto
{
    public function __construct(
        #[Groups('wallet-summary:read')]
        public readonly string $kind,
        #[Groups('wallet-summary:read')]
        public readonly string $currency,
        #[Groups('wallet-summary:read')]
        public readonly string $name,
        #[Groups('wallet-summary:read')]
        public readonly string $balance,
    ) {
    }
}
