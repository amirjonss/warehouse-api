<?php

declare(strict_types=1);

namespace App\Component\Expense;

use App\Component\Product\Enums\Currency;
use App\Entity\CashAccount;
use App\Entity\Expense;
use App\Entity\User;
use DateTime;
use DateTimeInterface;

class ExpenseFactory
{
    public function create(
        User $createdBy,
        string $description,
        string $amount,
        Currency $currency,
        ?DateTimeInterface $docDate = null,
        ?CashAccount $account = null
    ): Expense {
        $expense = new Expense();
        $expense
            ->setCreatedBy($createdBy)
            ->setCreatedAt(new DateTime())
            ->setDocDate($docDate ?? new DateTime())
            ->setDescription($description)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setAccount($account);

        return $expense;
    }
}
