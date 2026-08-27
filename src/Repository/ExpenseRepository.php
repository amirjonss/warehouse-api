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
     * Расход живёт в двух валютах и складывать их нельзя, поэтому итог всегда пара
     * чисел — как getSummary() у прибыли.
     *
     * @param string|null $from включительная нижняя граница doc_date (YYYY-MM-DD)
     * @param string|null $to   исключающая верхняя граница doc_date (YYYY-MM-DD)
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
     * Суммы расходов по дням за период — для столбчатой диаграммы одним запросом.
     * Валюты разнесены по колонкам: столбик рисуется по сумовой части, долларовая
     * показывается подписью, складывать их без курса нельзя.
     *
     * @param string|null $from включительная нижняя граница doc_date (YYYY-MM-DD)
     * @param string|null $to   исключающая верхняя граница doc_date (YYYY-MM-DD)
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
