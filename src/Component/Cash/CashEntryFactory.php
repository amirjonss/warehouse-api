<?php

declare(strict_types=1);

namespace App\Component\Cash;

use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Product\Enums\Currency;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\User;
use DateTime;

class CashEntryFactory
{
    /**
     * @param string $amount signed: + collected, − spent, handed over or short
     */
    public function create(
        CashEntryKind $kind,
        CashSession $session,
        string $amount,
        Currency $currency,
        CashEntryStatus $status,
        ?Payment $payment,
        ?Expense $expense,
        ?string $note,
        User $createdBy
    ): CashEntry {
        $entry = new CashEntry();
        $entry
            ->setOccurredAt(new DateTime())
            ->setKind($kind)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setPayment($payment)
            ->setExpense($expense)
            ->setNote($note)
            ->setCreatedBy($createdBy);

        $session->addEntry($entry);

        // The money physically leaves the seller when it is handed over, not when the
        // owner confirms it, so the balance moves for a DECLARED row as well.
        $this->addToBalance($session, $currency, $amount);

        if ($kind === CashEntryKind::HANDOVER && $status === CashEntryStatus::DECLARED) {
            // The handover amount is negative, while "declared" is a positive figure.
            $this->addToUnconfirmed($session, $currency, bcmul($amount, '-1', 2));
        }

        return $entry;
    }

    /**
     * The owner confirmed the money arrived: the balance in the bag already went down
     * when it was declared, so only the "unconfirmed" mark is cleared.
     */
    public function confirm(CashEntry $entry, User $confirmedBy): CashEntry
    {
        $entry
            ->setStatus(CashEntryStatus::CONFIRMED)
            ->setConfirmedBy($confirmedBy)
            ->setConfirmedAt(new DateTime());

        $this->addToUnconfirmed($entry->getSession(), $entry->getCurrency(), $entry->getAmount());

        return $entry;
    }

    private function addToBalance(CashSession $session, Currency $currency, string $delta): void
    {
        if ($currency === Currency::USD) {
            $session->setBalanceUsd(bcadd($session->getBalanceUsd() ?? '0', $delta, 2));

            return;
        }

        $session->setBalanceUzs(bcadd($session->getBalanceUzs() ?? '0', $delta, 2));
    }

    private function addToUnconfirmed(CashSession $session, Currency $currency, string $delta): void
    {
        if ($currency === Currency::USD) {
            $session->setUnconfirmedUsd(bcadd($session->getUnconfirmedUsd() ?? '0', $delta, 2));

            return;
        }

        $session->setUnconfirmedUzs(bcadd($session->getUnconfirmedUzs() ?? '0', $delta, 2));
    }
}
