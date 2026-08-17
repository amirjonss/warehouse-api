<?php

declare(strict_types=1);

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

    public function getDebtAging(): array
    {
        $sql = <<<'SQL'
            SELECT client_id, MIN(doc_date) AS oldest_debt_date
            FROM (
                SELECT d.client_id, s.doc_date, d.sale_id, d.currency, SUM(d.amount) AS balance
                FROM debts d
                JOIN sales s ON s.id = d.sale_id
                GROUP BY d.client_id, d.sale_id, s.doc_date, d.currency
            ) open_sale_currency
            WHERE balance > 0.01
            GROUP BY client_id
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['client_id']] = (string) $row['oldest_debt_date'];
        }

        return $result;
    }
}
