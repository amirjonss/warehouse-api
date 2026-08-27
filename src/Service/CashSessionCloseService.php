<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashEntryFactory;
use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Cash\Exceptions\SessionNotClosableException;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Core\Enums\CashSessionStatus;
use App\Component\Product\Enums\Currency;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashEntryRepository;
use App\Repository\CashSessionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Закрытие смены владельцем.
 *
 * Владелец вводит, сколько принял по каждой валюте. Всё, что числилось за
 * продавцом, но не принесено, становится строкой «недостача» — сумма остаётся
 * видимой в журнале, а не растворяется при обнулении остатка. Ради этой цифры
 * модуль и делался.
 */
class CashSessionCloseService
{
    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashEntryRepository $cashEntryRepository,
        private CashEntryFactory $cashEntryFactory,
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
            $this->assertNoDeclaredHandovers($session);

            foreach ([[Currency::USD, $acceptedUsd], [Currency::UZS, $acceptedUzs]] as [$currency, $accepted]) {
                $this->settleCurrency($session, $currency, $accepted, $note, $closedBy);
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
                // Владелец сам вводит сумму при закрытии — подтверждать нечего.
                CashEntryStatus::CONFIRMED,
                null,
                null,
                $note ?? 'Сдача при закрытии смены',
                $closedBy
            );
            $handover->setConfirmedBy($closedBy)->setConfirmedAt(new DateTime());
            $this->entityManager->persist($handover);
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
