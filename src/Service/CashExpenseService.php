<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\AccountEntryFactory;
use App\Component\Account\Enums\AccountEntryKind;
use App\Component\Account\Exceptions\InsufficientAccountBalanceException;
use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Expense\Exceptions\ExpenseSourceException;
use App\Component\Product\Enums\Currency;
use App\Component\User\CurrentUser;
use App\Entity\AccountEntry;
use App\Entity\CashAccount;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Entity\Expense;
use App\Repository\AccountEntryRepository;
use App\Repository\CashAccountRepository;
use App\Repository\CashEntryRepository;
use App\Repository\CashSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Money spent on business needs, out of one of exactly two places.
 *
 * A seller spends what is in their own bag: the session is looked up by the expense's
 * author, so nobody can reach into somebody else's float — the only way money travels
 * from a seller to the owner is a handover.
 *
 * The owner spends out of a company account, naming it on the document. Cash is the
 * reason the source has to be stated rather than derived: "paid in cash" does not say
 * whether it came out of a seller's bag or out of the safe.
 *
 * There are no advances either way: only money that is actually there can be spent, and
 * it is checked in its own currency. Dollars and sums are not interchangeable, and
 * "there is enough in total" is not an argument here.
 */
class CashExpenseService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryRepository $cashEntryRepository,
        private CashEntryFactory $cashEntryFactory,
        private AccountEntryRepository $accountEntryRepository,
        private CashAccountRepository $cashAccountRepository,
        private AccountEntryFactory $accountEntryFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Expense $expense): Expense
    {
        return $this->entityManager->wrapInTransaction(function () use ($expense) {
            if ($expense->getAccount() !== null) {
                return $this->spendFromAccount($expense);
            }

            // Always the author's own session: reaching into another seller's float would
            // make their closing shortage meaningless.
            $session = $this->cashSessionRepository->findOpenForUser($expense->getCreatedBy());

            if ($session === null) {
                throw new ExpenseSourceException($this->noSourceMessage($expense));
            }

            $this->entityManager->persist($expense);

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
            if ($expense->getAccount() !== null) {
                $this->refundToAccount($expense);
                $this->entityManager->remove($expense);

                return;
            }

            $session = $expense->getCashSession();

            if ($session === null) {
                // Predates the treasury: it never took money off anything.
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
     * The owner spending out of a company account. The currency has to match the account
     * it is taken from — there is no dollar bank account or card, so a dollar expense can
     * only ever come out of dollar cash.
     */
    private function spendFromAccount(Expense $expense): Expense
    {
        $account = $expense->getAccount();

        if ($account->getCurrency() !== $expense->getCurrency()) {
            throw new ExpenseSourceException(sprintf(
                'Счёт «%s» ведётся в %s — провести с него расход в %s нельзя.',
                $account->getName(),
                $account->getCurrency()->value,
                $expense->getCurrency()->value
            ));
        }

        $this->entityManager->persist($expense);

        $this->cashAccountRepository->lockAccounts([$account]);
        $this->assertEnoughOnAccount($account, $expense->getAmount());
        $this->entityManager->flush();

        $this->entityManager->persist($this->accountEntryFactory->create(
            AccountEntryKind::EXPENSE,
            $account,
            bcmul($expense->getAmount(), '-1', 2),
            null,
            null,
            null,
            // Copied into the journal so the row still reads sensibly once the expense
            // itself is deleted and the link is detached.
            sprintf('Расход: %s', $expense->getDescription()),
            $this->currentUser->getUser(),
            null,
            $expense
        ));

        return $expense;
    }

    /**
     * Deleting an account-backed expense puts the money back with an opposite row. It is
     * refused when the money has already moved on, for the same reason a cancelled card
     * payment is: a negative balance means the system is lying about the money.
     */
    private function refundToAccount(Expense $expense): void
    {
        $spent = '0';
        foreach ($this->accountEntryRepository->findBy(['expense' => $expense]) as $entry) {
            $spent = bcadd($spent, $entry->getAmount(), 2);
        }

        if (bccomp($spent, '0', 2) === 0) {
            return;
        }

        $account = $expense->getAccount();
        $this->cashAccountRepository->lockAccounts([$account]);

        $refund = bcmul($spent, '-1', 2);

        $this->entityManager->persist($this->accountEntryFactory->create(
            AccountEntryKind::EXPENSE,
            $account,
            $refund,
            null,
            null,
            null,
            sprintf('Сторно расхода: %s', $expense->getDescription()),
            $this->currentUser->getUser(),
            null,
            null
        ));

        // The expense is about to disappear while the journal rows must survive, so the
        // link is broken on the entities themselves, exactly as on the session side.
        foreach ($this->accountEntryRepository->findBy(['expense' => $expense]) as $entry) {
            $entry->setExpense(null);
        }
    }

    private function assertEnoughOnAccount(CashAccount $account, string $amount): void
    {
        if (bccomp($account->getBalance() ?? '0', $amount, 2) >= 0) {
            return;
        }

        throw new InsufficientAccountBalanceException(sprintf(
            'На счёте «%s» осталось %s %s — расход на %s %s провести нельзя.',
            $account->getName(),
            $account->getBalance(),
            $account->getCurrency()->value,
            $amount,
            $account->getCurrency()->value
        ));
    }

    /**
     * Only the owner may name an account, so only the owner is told that option exists.
     * This picks the wording; who is actually allowed to do what is enforced in
     * ExpenseCreateAction, next to the other authorisation checks.
     */
    private function noSourceMessage(Expense $expense): string
    {
        if (in_array('ROLE_ADMIN', $expense->getCreatedBy()->getRoles(), true)) {
            return 'Не указано, откуда взяты деньги: откройте смену или укажите счёт компании.';
        }

        return sprintf(
            'Нельзя провести расход: у сотрудника %s нет открытой смены. Откройте смену и повторите.',
            trim(sprintf(
                '%s %s',
                $expense->getCreatedBy()->getFirstName() ?? '',
                $expense->getCreatedBy()->getLastName() ?? ''
            )) ?: 'без имени'
        );
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
