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

/**
 * Создаёт строку журнала наличных и тут же двигает денормализованный остаток на
 * смене — ровно как DebtFactory двигает Client::debtUsd/debtUzs. Единственное
 * место, где меняются balance* и unconfirmed*: если баланс разошёлся с суммой
 * журнала, баг искать здесь.
 */
class CashEntryFactory
{
    /**
     * @param string $amount знаковая сумма: + приход, − расход, сдача, недостача
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

        // Деньги физически покидают продавца в момент сдачи, а не в момент
        // подтверждения, поэтому balance двигаем и для DECLARED-строки.
        $this->addToBalance($session, $currency, $amount);

        if ($kind === CashEntryKind::HANDOVER && $status === CashEntryStatus::DECLARED) {
            // Сумма сдачи отрицательная, а «заявлено» — величина положительная.
            $this->addToUnconfirmed($session, $currency, bcmul($amount, '-1', 2));
        }

        return $entry;
    }

    /**
     * Владелец подтвердил приём денег: остаток в сумке уже уменьшился при заявке,
     * снимается только пометка о неподтверждённом.
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
