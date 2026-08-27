<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Product\Enums\Currency;
use App\Component\User\CurrentUser;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Entity\Expense;
use App\Repository\CashEntryRepository;
use App\Repository\CashSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Расход из наличности подотчёта.
 *
 * Аванса нет: тратить можно только то, что реально собрано, поэтому сумма
 * проверяется против остатка именно в своей валюте — доллары и сумы не
 * взаимозаменяемы, и «в сумме хватает» здесь не аргумент.
 *
 * Если открытой смены нет, расход остаётся общим расходом компании (как было до
 * появления подотчёта): деньги не выходили из чьей-то сумки, отслеживать нечего.
 */
class CashExpenseService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryRepository $cashEntryRepository,
        private CashEntryFactory $cashEntryFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Expense $expense): Expense
    {
        return $this->entityManager->wrapInTransaction(function () use ($expense) {
            $session = $this->cashSessionRepository->findOpenForUser($expense->getCreatedBy());

            $this->entityManager->persist($expense);

            if ($session === null) {
                return $expense;
            }

            $this->cashSessionRepository->lockSessions([$session]);
            $this->assertEnoughCash($session, $expense);

            $expense->setCashSession($session);
            $this->entityManager->flush();

            $entry = $this->cashEntryFactory->create(
                CashEntryKind::EXPENSE,
                $session,
                bcmul($expense->getAmount(), '-1', 2),
                $expense->getCurrency(),
                CashEntryStatus::CONFIRMED,
                null,
                $expense,
                // Описание дублируем в журнал: при удалении расхода ссылка отвяжется,
                // а строка обязана остаться читаемой.
                sprintf('Расход: %s', $expense->getDescription()),
                $this->currentUser->getUser()
            );

            $this->entityManager->persist($entry);

            return $expense;
        });
    }

    /**
     * Удаление расхода возвращает деньги в кассу обратной строкой — журнал не
     * переписываем, как и у платежа.
     */
    public function delete(Expense $expense): void
    {
        $this->entityManager->wrapInTransaction(function () use ($expense) {
            $session = $expense->getCashSession();

            if ($session === null) {
                $this->entityManager->remove($expense);

                return;
            }

            if (!$session->isOpen()) {
                throw new CashSessionClosedException(sprintf(
                    'Расход относится к закрытой смене %s — удалить его уже нельзя. '
                    . 'Заведите отдельный документ на возврат.',
                    $session->getNumber()
                ));
            }

            $this->cashSessionRepository->lockSessions([$session]);

            foreach ($this->outstandingEntries($expense) as $entry) {
                $reversal = $this->cashEntryFactory->create(
                    CashEntryKind::EXPENSE,
                    $session,
                    bcmul($entry->getAmount(), '-1', 2),
                    $entry->getCurrency(),
                    CashEntryStatus::CONFIRMED,
                    null,
                    null,
                    sprintf('Сторно расхода: %s', $expense->getDescription()),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($reversal);
            }

            $this->entityManager->flush();

            // Расход сейчас исчезнет, а строки журнала обязаны остаться: рвём ссылку
            // прямым запросом, чтобы внешний ключ не держал удаление. Описание уже
            // продублировано в note, так что строка останется читаемой.
            $this->detachEntries($expense);

            $this->entityManager->remove($expense);
        });
    }

    /**
     * @return CashEntry[]
     */
    private function outstandingEntries(Expense $expense): array
    {
        return $this->cashEntryRepository->findBy(['expense' => $expense]);
    }

    private function detachEntries(Expense $expense): void
    {
        $this->entityManager
            ->createQuery('UPDATE ' . CashEntry::class . ' ce SET ce.expense = NULL WHERE ce.expense = :expense')
            ->setParameter('expense', $expense)
            ->execute();

        // Bulk-UPDATE проходит мимо identity map — освежаем объекты, иначе
        // финальный flush снова запишет в них старую ссылку.
        foreach ($this->outstandingEntries($expense) as $stale) {
            $this->entityManager->refresh($stale);
        }
    }

    private function assertEnoughCash(CashSession $session, Expense $expense): void
    {
        $currency = $expense->getCurrency();
        $balance = $currency === Currency::USD ? $session->getBalanceUsd() : $session->getBalanceUzs();

        if (bccomp($balance, $expense->getAmount(), 2) >= 0) {
            return;
        }

        throw new InsufficientCashException(sprintf(
            'В смене %s осталось %s %s — расход на %s %s провести нельзя.',
            $session->getNumber(),
            $balance,
            $currency->value,
            $expense->getAmount(),
            $currency->value
        ));
    }
}
