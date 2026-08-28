<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashSessionFactory;
use App\Component\Cash\Exceptions\SessionAlreadyOpenException;
use App\Component\Cash\Exceptions\SessionNumberTakenException;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class CashSessionOpenService
{
    /** The partial "one open session per seller" index from the migration. */
    private const OPEN_SESSION_INDEX = 'uniq_cash_sessions_open_user';

    public function __construct(
        private CashSessionRepository $cashSessionRepository,
        private CashSessionFactory $cashSessionFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function open(User $user, User $openedBy): CashSession
    {
        $existing = $this->cashSessionRepository->findOpenForUser($user);
        if ($existing !== null) {
            throw new SessionAlreadyOpenException(sprintf(
                'У сотрудника уже открыта смена %s — закройте её, прежде чем открывать новую.',
                $existing->getNumber()
            ));
        }

        $session = $this->cashSessionFactory->create($user, $openedBy);

        try {
            $this->entityManager->persist($session);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw $this->explainViolation($e, $session);
        }

        return $session;
    }

    private function explainViolation(UniqueConstraintViolationException $e, CashSession $session): SessionAlreadyOpenException|SessionNumberTakenException
    {
        if (stripos($e->getMessage(), self::OPEN_SESSION_INDEX) !== false) {
            return new SessionAlreadyOpenException(
                'Смена для этого сотрудника уже открыта — обновите страницу.',
                $e
            );
        }

        return new SessionNumberTakenException(
            sprintf('Номер %s уже занят другой сменой — повторите открытие.', $session->getNumber()),
            $e
        );
    }
}
