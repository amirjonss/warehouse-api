<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\AccountEntryFactory;
use App\Component\Account\Enums\AccountEntryKind;
use App\Component\Account\Exceptions\InvalidOpeningBalanceException;
use App\Component\Account\Exceptions\OpeningBalanceAlreadySetException;
use App\Entity\AccountEntry;
use App\Entity\CashAccount;
use App\Entity\User;
use App\Repository\AccountEntryRepository;
use App\Repository\CashAccountRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The balance an account starts life with, counted from the safe on go-live morning.
 *
 * It is a one-shot: a partial unique index allows exactly one OPENING row per account,
 * so an admin cannot quietly conjure money into the treasury later on.
 */
class CashAccountOpeningBalanceService
{
    public function __construct(
        private CashAccountRepository $cashAccountRepository,
        private AccountEntryRepository $accountEntryRepository,
        private AccountEntryFactory $accountEntryFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function set(CashAccount $account, string $amount, ?string $note, User $createdBy): AccountEntry
    {
        $this->assertPositive($amount);

        return $this->entityManager->wrapInTransaction(function () use ($account, $amount, $note, $createdBy) {
            $this->cashAccountRepository->lockAccounts([$account]);

            if ($this->accountEntryRepository->hasOpening($account)) {
                throw $this->alreadySet($account, null);
            }

            $entry = $this->accountEntryFactory->create(
                AccountEntryKind::OPENING,
                $account,
                $amount,
                null,
                null,
                null,
                $note ?? 'Начальный остаток',
                $createdBy
            );

            $this->entityManager->persist($entry);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                // The check above races two concurrent requests; the index does not.
                throw $this->alreadySet($account, $e);
            }

            return $entry;
        });
    }

    /**
     * Zero is refused along with negatives: a journal row of zero says nothing, and the
     * amount CHECK on account_entries rejects it anyway.
     */
    private function assertPositive(string $amount): void
    {
        if (bccomp($amount, '0', 2) > 0) {
            return;
        }

        throw new InvalidOpeningBalanceException('Начальный остаток должен быть больше нуля.');
    }

    private function alreadySet(CashAccount $account, ?UniqueConstraintViolationException $previous): OpeningBalanceAlreadySetException
    {
        return new OpeningBalanceAlreadySetException(
            sprintf('Начальный остаток для счёта «%s» уже введён — изменить его нельзя.', $account->getName()),
            $previous
        );
    }
}
