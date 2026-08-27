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
     * Заявленные, но не подтверждённые сдачи — пока они есть, смену закрывать нельзя.
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
     * Строки прихода по платежу — нужны при отмене платежа, чтобы понять, из какой
     * смены сторнировать и не сторнировать дважды.
     *
     * @return CashEntry[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->findBy(['payment' => $payment], ['id' => 'ASC']);
    }

    /**
     * Сумма журнала по валютам — источник истины для сверки с денормализованным
     * balance* на смене. Расхождение означает баг в CashEntryFactory.
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
