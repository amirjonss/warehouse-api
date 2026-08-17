<?php

declare(strict_types=1);

namespace App\Component\Expense\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

class ExpenseDailyDto
{
    /**
     * @param ExpenseDailyItemDto[] $items
     */
    public function __construct(
        #[Groups('expense-daily:read')]
        public readonly array $items,
    ) {
    }
}
