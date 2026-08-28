<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CashEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method CashEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method CashEntry[]    findAll()
 * @method CashEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CashEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashEntry::class);
    }

    /**
     * Handovers declared but not confirmed: while any remain, the session cannot close.
     *
     * @return CashEntry[]
     */
    public function findDeclaredHandovers(CashSession $session): array
    {
        return $this->findBy([
            'session' => $session,
            'kind' => CashEntryKind::HANDOVER,
            'status' => CashEntryStatus::DECLARED,
        ], ['id' => 'ASC']);
    }

    /**
     * The journal rows of one payment, needed when it is cancelled to work out which
     * session to reverse from and to avoid reversing twice.
     *
     * @return CashEntry[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->findBy(['payment' => $payment], ['id' => 'ASC']);
    }

    /**
     * Journal totals per currency: the source of truth to reconcile against the
     * denormalised balance* on the session. A mismatch means a bug in CashEntryFactory.
     *
     * @return array{USD: string, UZS: string}
     */
    public function getJournalTotals(CashSession $session): array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT
                    COALESCE(SUM(amount) FILTER (WHERE currency = 'USD'), 0) AS total_usd,
                    COALESCE(SUM(amount) FILTER (WHERE currency = 'UZS'), 0) AS total_uzs
                FROM cash_entries
                WHERE session_id = :id
            SQL,
            ['id' => $session->getId()]
        );

        return [
            'USD' => (string) $row['total_usd'],
            'UZS' => (string) $row['total_uzs'],
        ];
    }
}
