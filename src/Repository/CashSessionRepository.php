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
            $this->getEntityManager()->refresh($session, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * The session's turnover by payment method: everything the seller collected over the
     * period, including card and transfer, which never physically reached them. It is not
     * duplicated into the journal — the data already sits in the payments linked to the
     * session at posting time.
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
