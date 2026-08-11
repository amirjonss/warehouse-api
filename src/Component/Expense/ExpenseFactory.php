<?php

namespace App\Component\Expense;

use App\Entity\Expense;
use App\Entity\User;
use DateTime;
use DateTimeInterface;

class ExpenseFactory
{
    public function create(User $createdBy, string $description, string $amount, ?DateTimeInterface $docDate = null): Expense
    {
        $expense = new Expense();
        $expense
            ->setCreatedBy($createdBy)
            ->setCreatedAt(new DateTime())
            ->setDocDate($docDate ?? new DateTime())
            ->setDescription($description)
            ->setAmount($amount);

        return $expense;
    }
}
