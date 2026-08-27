<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Cash\Exceptions\NoOpenSessionException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Product\Enums\Currency;
use App\Component\User\CurrentUser;
use App\Entity\Payment;
use App\Repository\CashEntryRepository;
use App\Repository\CashSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Связывает платёж со сменой продавца.
 *
 * Смену получает любой проведённый платёж — иначе оборот смены будет неполным.
 * Но в журнал наличных (а значит и в остаток на руках) попадают только CASH:
 * карта и перечисление уходят прямо на счёт компании, продавец их не держал.
 */
class CashCollectService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryRepository $cashEntryRepository,
        private CashEntryFactory $cashEntryFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function record(Payment $payment): void
    {
        $isCash = $payment->getMethod() === PaymentMethod::CASH;
        $session = $this->cashSessionRepository->findOpenForUser($payment->getAcceptedBy());

        if ($session === null) {
            if ($isCash) {
                throw new NoOpenSessionException(sprintf(
                    'Нельзя принять наличные: у сотрудника %s нет открытой смены. Откройте смену и повторите.',
                    $this->userLabel($payment)
                ));
            }

            // Безналичный платёж без смены — законная ситуация: например, приход
            // на счёт принимает администратор, который наличных вообще не берёт.
            return;
        }

        $payment->setCashSession($session);

        if (!$isCash) {
            return;
        }

        $this->cashSessionRepository->lockSessions([$session]);

        $entry = $this->cashEntryFactory->create(
            CashEntryKind::COLLECT,
            $session,
            $payment->getAmount(),
            $payment->getCurrency(),
            CashEntryStatus::CONFIRMED,
            $payment,
            null,
            null,
            $this->currentUser->getUser()
        );

        $this->entityManager->persist($entry);
    }

    /**
     * Отмена платежа: не удаляем строку прихода, а добавляем обратную — история
     * остаётся доказательством для обеих сторон, как и у Debt.
     */
    public function reverse(Payment $payment): void
    {
        $session = $payment->getCashSession();
        if ($session === null) {
            return;
        }

        $outstanding = $this->outstandingByCurrency($payment);
        if ($outstanding === []) {
            // Безналичный платёж либо приход уже сторнирован — второй раз не пишем.
            return;
        }

        if (!$session->isOpen()) {
            throw new CashSessionClosedException(sprintf(
                'Платёж «%s» относится к закрытой смене %s — сторнировать наличные уже некуда. '
                . 'Проведите возврат отдельным документом.',
                $payment->getNumber(),
                $session->getNumber()
            ));
        }

        $this->cashSessionRepository->lockSessions([$session]);

        foreach ($outstanding as $currencyValue => $amount) {
            $currency = Currency::from($currencyValue);
            $reversal = bcmul($amount, '-1', 2);

            $this->assertCashStillInSession($payment, $session->getBalanceUsd(), $session->getBalanceUzs(), $currency, $reversal);

            $entry = $this->cashEntryFactory->create(
                CashEntryKind::COLLECT,
                $session,
                $reversal,
                $currency,
                CashEntryStatus::CONFIRMED,
                $payment,
                null,
                sprintf('Сторно платежа «%s»', $payment->getNumber()),
                $this->currentUser->getUser()
            );

            $this->entityManager->persist($entry);
        }
    }

    /**
     * Сколько по этому платежу реально числится в журнале сейчас: приход минус уже
     * записанные сторно. Ноль означает, что сторнировать нечего.
     *
     * @return array<string, string> валюта => сумма
     */
    private function outstandingByCurrency(Payment $payment): array
    {
        $totals = [];

        foreach ($this->cashEntryRepository->findByPayment($payment) as $entry) {
            $key = $entry->getCurrency()->value;
            $totals[$key] = bcadd($totals[$key] ?? '0', $entry->getAmount(), 2);
        }

        return array_filter($totals, static fn (string $total) => bccomp($total, '0', 2) !== 0);
    }

    /**
     * Деньги могли быть уже потрачены или сданы владельцу — тогда сторно увело бы
     * остаток в минус. Минус в кассе означает, что система врёт про наличные,
     * поэтому такую отмену запрещаем и объясняем, что делать.
     */
    private function assertCashStillInSession(
        Payment $payment,
        string $balanceUsd,
        string $balanceUzs,
        Currency $currency,
        string $reversal
    ): void {
        $balance = $currency === Currency::USD ? $balanceUsd : $balanceUzs;

        if (bccomp(bcadd($balance, $reversal, 2), '0', 2) >= 0) {
            return;
        }

        throw new InsufficientCashException(sprintf(
            'Нельзя отменить платёж «%s»: в смене осталось %s %s, а вернуть нужно %s %s — '
            . 'часть денег уже потрачена или сдана владельцу.',
            $payment->getNumber(),
            $balance,
            $currency->value,
            bcmul($reversal, '-1', 2),
            $currency->value
        ));
    }

    private function userLabel(Payment $payment): string
    {
        $user = $payment->getAcceptedBy();

        return trim(sprintf('%s %s', $user?->getFirstName() ?? '', $user?->getLastName() ?? '')) ?: 'без имени';
    }
}
