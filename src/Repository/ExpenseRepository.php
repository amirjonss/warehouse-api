<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Expense|null find($id, $lockMode = null, $lockVersion = null)
 * @method Expense|null findOneBy(array $criteria, array $orderBy = null)
 * @method Expense[]    findAll()
 * @method Expense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * An expense lives in two currencies and they cannot be added up, so the total is
     * always a pair of numbers, just like getSummary() on profit.
     *
     * @param string|null $from inclusive lower bound on doc_date (YYYY-MM-DD)
     * @param string|null $to   exclusive upper bound on doc_date (YYYY-MM-DD)
     *
     * @return array{totalUsd: string, totalUzs: string}
     */
    public function getSummary(?string $from, ?string $to): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            "COALESCE(SUM(amount) FILTER (WHERE currency = 'USD'), 0) AS total_usd",
            "COALESCE(SUM(amount) FILTER (WHERE currency = 'UZS'), 0) AS total_uzs",
        )->from('expenses');

        if ($from !== null) {
            $qb->andWhere('doc_date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('doc_date < :to')->setParameter('to', $to);
        }

        $row = $qb->executeQuery()->fetchAssociative();

        return [
            'totalUsd' => (string) $row['total_usd'],
            'totalUzs' => (string) $row['total_uzs'],
        ];
    }

    /**
     * Daily expense totals over a period, for the bar chart in a single query.
     * The currencies live in separate columns: the bar is drawn from the UZS part and the
     * USD one is shown as a label, because adding them up without a rate is meaningless.
     *
     * @param string|null $from inclusive lower bound on doc_date (YYYY-MM-DD)
     * @param string|null $to   exclusive upper bound on doc_date (YYYY-MM-DD)
     *
     * @return array<int, array{doc_date: string, total_usd: string, total_uzs: string, count: string}>
     */
    public function getDailyTotals(?string $from, ?string $to): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            'doc_date',
            "COALESCE(SUM(amount) FILTER (WHERE currency = 'USD'), 0) AS total_usd",
            "COALESCE(SUM(amount) FILTER (WHERE currency = 'UZS'), 0) AS total_uzs",
            'COUNT(*) AS count',
        )
            ->from('expenses')
            ->groupBy('doc_date')
            ->orderBy('doc_date');

        if ($from !== null) {
            $qb->andWhere('doc_date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('doc_date < :to')->setParameter('to', $to);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
