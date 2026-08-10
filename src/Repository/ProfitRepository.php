<?php

namespace App\Repository;

use App\Entity\Profit;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Profit|null find($id, $lockMode = null, $lockVersion = null)
 * @method Profit|null findOneBy(array $criteria, array $orderBy = null)
 * @method Profit[]    findAll()
 * @method Profit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profit::class);
    }

    /**
     * @return array<string, string> прибыль, индексированная по коду валюты
     */
    public function getTotalForSale(Sale $sale): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.currency AS currency, SUM(p.profit) AS total')
            ->andWhere('p.sale = :sale')
            ->setParameter('sale', $sale)
            ->groupBy('p.currency')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['currency']] = (string) $row['total'];
        }

        return $result;
    }

    public function getSummary(?string $from, ?string $to): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            "COALESCE(SUM(profit) FILTER (WHERE currency = 'USD'), 0) AS total_usd",
            "COALESCE(SUM(profit) FILTER (WHERE currency = 'UZS'), 0) AS total_uzs",
        )->from('profits');

        if ($from !== null) {
            $qb->andWhere('occurred_at >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('occurred_at < :to')->setParameter('to', $to);
        }

        $row = $qb->executeQuery()->fetchAssociative();

        return [
            'totalUsd' => (string) $row['total_usd'],
            'totalUzs' => (string) $row['total_uzs'],
        ];
    }
}
