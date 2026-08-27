<?php

declare(strict_types=1);

namespace App\Component\Expense\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class ExpenseDailyItemDto
{
    public function __construct(
        #[Groups('expense-daily:read')]
        public readonly string $date,
        #[Groups('expense-daily:read')]
        public readonly string $totalUsd,
        #[Groups('expense-daily:read')]
        public readonly string $totalUzs,
        #[Groups('expense-daily:read')]
        public readonly int $count,
    ) {
    }
}
