<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashEntryNotConfirmableException;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Product\Enums\Currency;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

class CashHandoverService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryFactory $cashEntryFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function declareHandover(
        CashSession $session,
        string $amount,
        Currency $currency,
        ?string $note,
        User $declaredBy
    ): CashEntry {
        return $this->entityManager->wrapInTransaction(function () use ($session, $amount, $currency, $note, $declaredBy) {
            $this->assertOpen($session);
            $this->assertPositive($amount);

            $this->cashSessionRepository->lockSessions([$session]);
            $this->assertEnoughCash($session, $amount, $currency);

            $entry = $this->cashEntryFactory->create(
                CashEntryKind::HANDOVER,
                $session,
                bcmul($amount, '-1', 2),
                $currency,
                CashEntryStatus::DECLARED,
                null,
                null,
                $note,
                $declaredBy
            );

            $this->entityManager->persist($entry);

            return $entry;
        });
    }

    public function confirm(CashEntry $entry, User $confirmedBy): CashEntry
    {
        return $this->entityManager->wrapInTransaction(function () use ($entry, $confirmedBy) {
            if ($entry->getKind() !== CashEntryKind::HANDOVER) {
                throw new CashEntryNotConfirmableException('Подтвердить можно только сдачу денег.');
            }

            if ($entry->getStatus() !== CashEntryStatus::DECLARED) {
                throw new CashEntryNotConfirmableException('Эта сдача уже подтверждена.');
            }

            $session = $entry->getSession();
            $this->assertOpen($session);
            $this->cashSessionRepository->lockSessions([$session]);

            return $this->cashEntryFactory->confirm($entry, $confirmedBy);
        });
    }

    private function assertOpen(CashSession $session): void
    {
        if ($session->isOpen()) {
            return;
        }

        throw new CashSessionClosedException(sprintf('Смена %s уже закрыта.', $session->getNumber()));
    }

    private function assertPositive(string $amount): void
    {
        if (bccomp($amount, '0', 2) > 0) {
            return;
        }

        throw new InsufficientCashException('Сумма сдачи должна быть больше нуля.');
    }

    private function assertEnoughCash(CashSession $session, string $amount, Currency $currency): void
    {
        $balance = $currency === Currency::USD ? $session->getBalanceUsd() : $session->getBalanceUzs();

        if (bccomp($balance, $amount, 2) >= 0) {
            return;
        }

        throw new InsufficientCashException(sprintf(
            'В смене %s числится %s %s — сдать %s %s нельзя.',
            $session->getNumber(),
            $balance,
            $currency->value,
            $amount,
            $currency->value
        ));
    }
}
