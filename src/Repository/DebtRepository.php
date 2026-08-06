<?php

namespace App\Repository;

use App\Component\Product\Enums\Currency;
use App\Entity\Debt;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Debt|null find($id, $lockMode = null, $lockVersion = null)
 * @method Debt|null findOneBy(array $criteria, array $orderBy = null)
 * @method Debt[]    findAll()
 * @method Debt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DebtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Debt::class);
    }

    /**
     * @return array<string, string> баланс, индексированный по коду валюты
     */
    public function getBalanceForSale(Sale $sale): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.currency AS currency, SUM(d.amount) AS total')
            ->andWhere('d.sale = :sale')
            ->setParameter('sale', $sale)
            ->groupBy('d.currency')
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            /** @var $currency Currency */
            $currency = $row['currency'];
            $result[$currency->value] = (string) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<int, array{usd: string, uzs: string}> баланс, индексировано по client id
     */
    public function getBalanceByClient(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.client) AS clientId, d.currency AS currency, SUM(d.amount) AS total')
            ->groupBy('d.client', 'd.currency')
            ->getQuery()
            ->getResult();
        $result = [];

        foreach ($rows as $row) {
            /** @var $currency Currency */
            $currency = $row['currency'];
            $clientId = (int) $row['clientId'];
            $result[$clientId] ??= ['usd' => '0', 'uzs' => '0'];
            $result[$clientId][strtolower($currency->value)] = (string) $row['total'];
        }

        return $result;
    }
}
