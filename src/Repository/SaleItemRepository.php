<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SaleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SaleItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method SaleItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method SaleItem[]    findAll()
 * @method SaleItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItem::class);
    }

    /**
     * Top products by quantity sold over a period (posted sales only).
     *
     * @param string|null $from inclusive lower bound on doc_date (YYYY-MM-DD)
     * @param string|null $to   exclusive upper bound on doc_date (YYYY-MM-DD)
     *
     * @return array<int, array{productId: int, productName: string, quantity: string, totalUsd: string, totalUzs: string}>
     */
    public function getTopProducts(?string $from, ?string $to, int $limit): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            'p.id AS product_id',
            'p.name AS product_name',
            'SUM(si.quantity) AS quantity',
            "COALESCE(SUM(si.total) FILTER (WHERE si.currency = 'USD'), 0) AS total_usd",
            "COALESCE(SUM(si.total) FILTER (WHERE si.currency = 'UZS'), 0) AS total_uzs",
        )
            ->from('sale_items', 'si')
            ->join('si', 'sales', 's', 's.id = si.sale_id')
            ->join('si', 'product', 'p', 'p.id = si.product_id')
            ->andWhere("s.status = 'posted'")
            ->groupBy('p.id, p.name')
            ->orderBy('quantity', 'DESC')
            ->setMaxResults($limit);

        if ($from !== null) {
            $qb->andWhere('s.doc_date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('s.doc_date < :to')->setParameter('to', $to);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
