<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\AccountEntryFactory;
use App\Component\Account\Enums\AccountEntryKind;
use App\Component\Account\Exceptions\InsufficientAccountBalanceException;
use App\Component\Core\Enums\DocStatus;
use App\Component\MoneyTransfer\Exceptions\MoneyTransferStatusTransitionException;
use App\Component\User\CurrentUser;
use App\Entity\CashAccount;
use App\Entity\MoneyTransfer;
use App\Repository\CashAccountRepository;
use App\Repository\MoneyTransferRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class MoneyTransferChangeStatusService
{
    public function __construct(
        private MoneyTransferRepository $moneyTransferRepository,
        private CashAccountRepository $cashAccountRepository,
        private AccountEntryFactory $accountEntryFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(MoneyTransfer $transfer): MoneyTransfer
    {
        $previousStatus = $this->getPreviousStatus($transfer);
        $newStatus = $transfer->getStatus();

        if ($previousStatus === $newStatus) {
            return $transfer;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new MoneyTransferStatusTransitionException('A posted money transfer cannot be moved back to draft.');
        }

        if ($previousStatus === DocStatus::CANCELLED) {
            throw new MoneyTransferStatusTransitionException(
                'Отменённый перевод нельзя провести заново — создайте новый документ.'
            );
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($transfer, $previousStatus);
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($transfer, $previousStatus);
        }

        $this->entityManager->flush();

        return $transfer;
    }

    private function post(MoneyTransfer $transfer, ?DocStatus $previousStatus): MoneyTransfer
    {
        return $this->entityManager->wrapInTransaction(function () use ($transfer, $previousStatus) {
            $this->moneyTransferRepository->lockMoneyTransfers([$transfer]);
            $this->assertNotChangedConcurrently($transfer, $previousStatus);

            $from = $transfer->getFromAccount();
            $to = $transfer->getToAccount();
            $this->cashAccountRepository->lockAccounts([$from, $to]);

            $this->assertEnoughBalance($from, $transfer->getAmountSent());

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::TRANSFER_OUT,
                $from,
                bcmul($transfer->getAmountSent(), '-1', 2),
                null,
                null,
                $transfer,
                $this->noteFor($transfer),
                $this->currentUser->getUser()
            ));

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::TRANSFER_IN,
                $to,
                $transfer->getAmountReceived(),
                null,
                null,
                $transfer,
                $this->noteFor($transfer),
                $this->currentUser->getUser()
            ));

            $transfer->setPostedAt(new DateTime());

            return $transfer;
        });
    }

    private function cancel(MoneyTransfer $transfer, ?DocStatus $previousStatus): MoneyTransfer
    {
        return $this->entityManager->wrapInTransaction(function () use ($transfer, $previousStatus) {
            $this->moneyTransferRepository->lockMoneyTransfers([$transfer]);
            $this->assertNotChangedConcurrently($transfer, $previousStatus);

            $from = $transfer->getFromAccount();
            $to = $transfer->getToAccount();
            $this->cashAccountRepository->lockAccounts([$from, $to]);

            // The money that arrived may already have been spent, and taking it back out
            // would push the receiving account below zero — which would be a lie.
            $this->assertEnoughBalance($to, $transfer->getAmountReceived());

            $note = sprintf('Сторно перевода «%s»', $transfer->getNumber());

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::TRANSFER_IN,
                $from,
                $transfer->getAmountSent(),
                null,
                null,
                $transfer,
                $note,
                $this->currentUser->getUser()
            ));

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::TRANSFER_OUT,
                $to,
                bcmul($transfer->getAmountReceived(), '-1', 2),
                null,
                null,
                $transfer,
                $note,
                $this->currentUser->getUser()
            ));

            return $transfer;
        });
    }

    private function assertEnoughBalance(CashAccount $account, string $amount): void
    {
        if (bccomp($account->getBalance() ?? '0', $amount, 2) >= 0) {
            return;
        }

        throw new InsufficientAccountBalanceException(sprintf(
            'На счёте «%s» осталось %s %s — списать %s %s нельзя.',
            $account->getName(),
            $account->getBalance(),
            $account->getCurrency()->value,
            $amount,
            $account->getCurrency()->value
        ));
    }

    private function assertNotChangedConcurrently(MoneyTransfer $transfer, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null
            && $this->moneyTransferRepository->getCurrentStatus($transfer->getId()) !== $expectedStatus->value) {
            throw new MoneyTransferStatusTransitionException(
                'This money transfer was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function noteFor(MoneyTransfer $transfer): string
    {
        return $transfer->getNote() ?? sprintf('Перевод «%s»', $transfer->getNumber());
    }

    private function getPreviousStatus(MoneyTransfer $transfer): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($transfer)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
