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
}
