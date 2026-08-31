<?php

declare(strict_types=1);

namespace App\Component\Account;

use App\Component\Account\Enums\AccountEntryKind;
use App\Entity\AccountEntry;
use App\Entity\CashAccount;
use App\Entity\CashEntry;
use App\Entity\Expense;
use App\Entity\MoneyTransfer;
use App\Entity\Payment;
use App\Entity\SupplierPayment;
use App\Entity\User;
use DateTime;

/**
 * The only place CashAccount.balance ever moves, mirroring how CashEntryFactory owns
 * the session balance and DebtFactory owns the client's running debt.
 */
class AccountEntryFactory
{
    /**
     * @param string $amount signed: + money in, − money out
     */
    public function create(
        AccountEntryKind $kind,
        CashAccount $account,
        string $amount,
        ?CashEntry $cashEntry,
        ?Payment $payment,
        ?MoneyTransfer $moneyTransfer,
        ?string $note,
        User $createdBy,
        ?SupplierPayment $supplierPayment = null,
        ?Expense $expense = null
    ): AccountEntry {
        $entry = new AccountEntry();
        $entry
            ->setOccurredAt(new DateTime())
            ->setKind($kind)
            ->setAmount($amount)
            ->setCashEntry($cashEntry)
            ->setPayment($payment)
            ->setMoneyTransfer($moneyTransfer)
            ->setSupplierPayment($supplierPayment)
            ->setExpense($expense)
            ->setNote($note)
            ->setCreatedBy($createdBy);

        $account->addEntry($entry);
        $account->setBalance(bcadd($account->getBalance() ?? '0', $amount, 2));

        return $entry;
    }
}
