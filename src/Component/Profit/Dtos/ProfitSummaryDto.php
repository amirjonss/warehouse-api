<?php

namespace App\Component\Profit\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class ProfitSummaryDto
{
    public function __construct(
        #[Groups(['profit-sum:read'])]
        private readonly string $totalUsd,
        #[Groups(['profit-sum:read'])]
        private readonly string $totalUzs,
    ) {
    }

    public function getTotalUsd(): string
    {
        return $this->totalUsd;
    }

    public function getTotalUzs(): string
    {
        return $this->totalUzs;
    }
}
