<?php

declare(strict_types=1);

namespace App\Component\Expense\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class ExpenseSummaryDto
{
    public function __construct(
        #[Groups('expense-summary:read')]
        public readonly string $totalUsd,
        #[Groups('expense-summary:read')]
        public readonly string $totalUzs,
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
