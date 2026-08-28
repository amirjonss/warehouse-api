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
 * An expense paid out of the float's cash.
 *
 * There are no advances: only what has actually been collected can be spent, so the
 * amount is checked against the balance in its own currency. Dollars and sums are not
 * interchangeable, and "there is enough in total" is not an argument here.
 *
 * With no open session the expense stays an ordinary company expense, as it was before
 * the float existed: the money never left anyone's bag, so there is nothing to track.
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
                // The description is copied into the journal: deleting the expense detaches
                // the link, and the row still has to read sensibly.
                sprintf('Расход: %s', $expense->getDescription()),
                $this->currentUser->getUser()
            );

            $this->entityManager->persist($entry);

            return $expense;
        });
    }

    /**
     * Deleting an expense returns the money with an opposite row: the journal is never
     * rewritten, exactly as with a payment.
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

            $entries = $this->outstandingEntries($expense);

            foreach ($entries as $entry) {
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

            // The expense is about to disappear while the journal rows must survive, so the
            // link is broken on the entities themselves. Doctrine runs UPDATE before DELETE,
            // so the foreign key will not block the removal. The description is already
            // duplicated into note, so the row stays readable.
            foreach ($entries as $entry) {
                $entry->setExpense(null);
            }

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
