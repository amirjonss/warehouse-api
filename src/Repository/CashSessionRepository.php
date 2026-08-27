<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Core\Enums\CashSessionStatus;
use App\Entity\CashSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CashSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method CashSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method CashSession[]    findAll()
 * @method CashSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CashSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashSession::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('cs')
            ->select('cs.number')
            ->orderBy('cs.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * Открытая смена у продавца всегда одна — это гарантирует частичный уникальный
     * индекс в БД, здесь просто достаём её.
     */
    public function findOpenForUser(User $user): ?CashSession
    {
        return $this->findOneBy(['user' => $user, 'status' => CashSessionStatus::OPEN]);
    }

    /**
     * @param CashSession[] $sessions
     */
    public function lockSessions(array $sessions): void
    {
        $unique = [];
        foreach ($sessions as $session) {
            $unique[$session->getId()] = $session;
        }
        ksort($unique);

        foreach ($unique as $session) {
            $this->getEntityManager()->lock($session, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM cash_sessions WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Оборот смены по способам оплаты — то, что продавец собрал за период, включая
     * карту и перечисление, которые до него физически не доходили. Не дублируем это
     * в журнал: данные уже лежат в payments, связанных со сменой при проведении.
     *
     * @return array<int, array{method: string, currency: string, total: string, count: int}>
     */
    public function getTurnover(CashSession $session): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT method, currency, SUM(amount) AS total, COUNT(*) AS count
                FROM payments
                WHERE cash_session_id = :id AND status = 'posted'
                GROUP BY method, currency
                ORDER BY method, currency
            SQL,
            ['id' => $session->getId()]
        );

        return array_map(
            static fn (array $row) => [
                'method' => (string) $row['method'],
                'currency' => (string) $row['currency'],
                'total' => (string) $row['total'],
                'count' => (int) $row['count'],
            ],
            $rows
        );
    }

    /**
     * Сколько наличных сейчас на руках у всех продавцов вместе — для плитки на
     * дашборде владельца. Неподтверждённые сдачи считаются отдельно: деньги уже
     * не у продавца, но компания их ещё не признала полученными.
     *
     * @return array{balanceUsd: string, balanceUzs: string, unconfirmedUsd: string, unconfirmedUzs: string, openSessions: int}
     */
    public function getTotalOnHands(): array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT
                    COALESCE(SUM(balance_usd), 0) AS balance_usd,
                    COALESCE(SUM(balance_uzs), 0) AS balance_uzs,
                    COALESCE(SUM(unconfirmed_usd), 0) AS unconfirmed_usd,
                    COALESCE(SUM(unconfirmed_uzs), 0) AS unconfirmed_uzs,
                    COUNT(*) AS open_sessions
                FROM cash_sessions
                WHERE status = 'open'
            SQL
        );

        return [
            'balanceUsd' => (string) $row['balance_usd'],
            'balanceUzs' => (string) $row['balance_uzs'],
            'unconfirmedUsd' => (string) $row['unconfirmed_usd'],
            'unconfirmedUzs' => (string) $row['unconfirmed_uzs'],
            'openSessions' => (int) $row['open_sessions'],
        ];
    }
}
