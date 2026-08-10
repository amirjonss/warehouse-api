<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function getDebtSummary(): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE debt_usd > 0 OR debt_uzs > 0) AS count,
                COALESCE(SUM(debt_usd), 0) AS total_debt_usd,
                COALESCE(SUM(debt_uzs), 0) AS total_debt_uzs
            FROM clients
            SQL;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql);

        return [
            'count' => (int) $row['count'],
            'totalDebtUsd' => (string) $row['total_debt_usd'],
            'totalDebtUzs' => (string) $row['total_debt_uzs'],
        ];
    }
}
