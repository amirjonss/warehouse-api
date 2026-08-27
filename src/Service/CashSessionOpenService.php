<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cash\CashSessionFactory;
use App\Component\Cash\Exceptions\SessionAlreadyOpenException;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class CashSessionOpenService
{
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
        } catch (UniqueConstraintViolationException) {
            // Две вкладки нажали «Открыть смену» одновременно: проверка выше их
            // обеих пропустила, а частичный индекс — нет. Это не ошибка сервера.
            throw new SessionAlreadyOpenException(
                'Смена для этого сотрудника уже открыта — обновите страницу.'
            );
        }

        return $session;
    }
}
