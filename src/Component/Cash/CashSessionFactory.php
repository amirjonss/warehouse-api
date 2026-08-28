<?php

declare(strict_types=1);

namespace App\Component\Cash;

use App\Component\Core\Enums\CashSessionStatus;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use DateTime;

class CashSessionFactory
{
    private const NUMBER_PREFIX = 'CS-';
    private const NUMBER_LENGTH = 5;

    public function __construct(private readonly CashSessionRepository $cashSessionRepository)
    {
    }

    public function create(User $user, User $openedBy): CashSession
    {
        $session = new CashSession();
        $session
            ->setNumber($this->generateNumber())
            ->setUser($user)
            ->setOpenedBy($openedBy)
            ->setOpenedAt(new DateTime())
            ->setStatus(CashSessionStatus::OPEN)
            ->setBalanceUsd('0.00')
            ->setBalanceUzs('0.00')
            ->setUnconfirmedUsd('0.00')
            ->setUnconfirmedUzs('0.00');

        return $session;
    }

    private function generateNumber(): string
    {
        $sequence = 1;
        $lastNumber = $this->cashSessionRepository->findLastNumber();
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
