<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\AccountEntryFactory;
use App\Component\Account\CashAccountResolver;
use App\Component\Account\Enums\AccountEntryKind;
use App\Component\Account\Enums\CashAccountKind;
use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Cash\Exceptions\SessionNotClosableException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Core\Enums\CashSessionStatus;
use App\Component\Product\Enums\Currency;
use App\Entity\CashAccount;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashAccountRepository;
use App\Repository\CashEntryRepository;
use App\Repository\CashSessionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Closing a session, done by the owner.
 *
 * The owner enters how much they took in per currency. Anything that was on the seller's
 * account but never arrived becomes a "shortage" row: the figure stays visible in the
 * journal instead of dissolving when the balance is zeroed. That number is the reason
 * this module exists.
 */
class CashSessionCloseService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryRepository $cashEntryRepository,
        private CashEntryFactory $cashEntryFactory,
        private CashAccountRepository $cashAccountRepository,
        private CashAccountResolver $cashAccountResolver,
        private AccountEntryFactory $accountEntryFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function close(
        CashSession $session,
        string $acceptedUsd,
        string $acceptedUzs,
        ?string $note,
        User $closedBy
    ): CashSession {
        return $this->entityManager->wrapInTransaction(function () use ($session, $acceptedUsd, $acceptedUzs, $note, $closedBy) {
            if (!$session->isOpen()) {
                throw new CashSessionClosedException(sprintf('Смена %s уже закрыта.', $session->getNumber()));
            }

            $this->cashSessionRepository->lockSessions([$session]);

            // While we waited for the lock, another admin could have closed the session.
            if (!$session->isOpen()) {
                throw new CashSessionClosedException(sprintf('Смена %s уже закрыта.', $session->getNumber()));
            }

            $this->assertNoDeclaredHandovers($session);

            // Both accounts are locked up front, unconditionally, so the order stays
            // deterministic even when only one currency is actually settled. This must
            // come after the status re-check above: a second close has to bail on the
            // status rather than block on an account.
            $accounts = [
                Currency::USD->value => $this->cashAccountResolver->forKind(CashAccountKind::CASH, Currency::USD),
                Currency::UZS->value => $this->cashAccountResolver->forKind(CashAccountKind::CASH, Currency::UZS),
            ];
            $this->cashAccountRepository->lockAccounts($accounts);

            foreach ([[Currency::USD, $acceptedUsd], [Currency::UZS, $acceptedUzs]] as [$currency, $accepted]) {
                $this->settleCurrency($session, $currency, $accepted, $accounts[$currency->value], $note, $closedBy);
            }

            $session
                ->setStatus(CashSessionStatus::CLOSED)
                ->setClosedAt(new DateTime())
                ->setClosedBy($closedBy);

            return $session;
        });
    }

    private function settleCurrency(
        CashSession $session,
        Currency $currency,
        string $accepted,
        CashAccount $account,
        ?string $note,
        User $closedBy
    ): void {
        $balance = $currency === Currency::USD ? $session->getBalanceUsd() : $session->getBalanceUzs();

        if (bccomp($accepted, '0', 2) < 0) {
            throw new InsufficientCashException('Принятая сумма не может быть отрицательной.');
        }

        if (bccomp($accepted, $balance, 2) > 0) {
            throw new InsufficientCashException(sprintf(
                'За продавцом числится %s %s — принять %s %s нельзя.',
                $balance,
                $currency->value,
                $accepted,
                $currency->value
            ));
        }

        if (bccomp($accepted, '0', 2) > 0) {
            $handover = $this->cashEntryFactory->create(
                CashEntryKind::HANDOVER,
                $session,
                bcmul($accepted, '-1', 2),
                $currency,
                // The owner enters the amount themselves at closing: nothing left to confirm.
                CashEntryStatus::CONFIRMED,
                null,
                null,
                $note ?? 'Сдача при закрытии смены',
                $closedBy
            );
            $handover->setConfirmedBy($closedBy)->setConfirmedAt(new DateTime());
            $this->entityManager->persist($handover);

            // The owner counted this money themselves, so it reaches the treasury at
            // once. A shortage writes nothing here — that money never arrived.
            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::HANDOVER,
                $account,
                $accepted,
                $handover,
                null,
                null,
                sprintf('Сдача при закрытии смены %s', $session->getNumber()),
                $closedBy
            ));
        }

        $shortage = bcsub($balance, $accepted, 2);
        if (bccomp($shortage, '0', 2) <= 0) {
            return;
        }

        $entry = $this->cashEntryFactory->create(
            CashEntryKind::SHORTAGE,
            $session,
            bcmul($shortage, '-1', 2),
            $currency,
            CashEntryStatus::CONFIRMED,
            null,
            null,
            sprintf('Недостача при закрытии смены: %s %s', $shortage, $currency->value),
            $closedBy
        );
        $this->entityManager->persist($entry);
    }

    private function assertNoDeclaredHandovers(CashSession $session): void
    {
        $declared = $this->cashEntryRepository->findDeclaredHandovers($session);

        if ($declared === []) {
            return;
        }

        throw new SessionNotClosableException(sprintf(
            'В смене %s есть неподтверждённые сдачи (%d шт.) — подтвердите их, прежде чем закрывать смену.',
            $session->getNumber(),
            count($declared)
        ));
    }
}
