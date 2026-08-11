<?php

namespace App\Component\Expense\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class ExpenseSummaryDto
{
    public function __construct(
        #[Groups('expense-summary:read')]
        public readonly string $totalAmount,
    ) {
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }
}
