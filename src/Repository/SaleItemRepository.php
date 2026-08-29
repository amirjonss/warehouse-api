<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Product\Enums\AbcMetric;
use App\Component\Product\Enums\Currency;
use App\Entity\SaleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Query\QueryBuilder;
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

    /**
     * Per-product totals for the ABC analysis, ranked best first.
     *
     * Money is never converted: a currency has to be named, and only lines in it are counted.
     * Quantity is not money, so it ignores the currency and ranks the whole catalogue at once.
     *
     * @param string|null $from inclusive lower bound on doc_date (YYYY-MM-DD)
     * @param string|null $to   exclusive upper bound on doc_date (YYYY-MM-DD)
     *
     * @return array<int, array{id: int, name: string, category_name: string|null, unit: string|null, value: string}>
     */
    public function getAbcTotals(
        AbcMetric $metric,
        ?string $from,
        ?string $to,
        ?int $categoryId,
        ?Currency $currency,
    ): array {
        $value = $metric === AbcMetric::QUANTITY ? 'SUM(si.quantity)' : 'SUM(si.total)';

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            'p.id AS id',
            'p.name AS name',
            'c.name AS category_name',
            'p.unit AS unit',
            $value . ' AS value',
        )
            ->from('sale_items', 'si')
            ->join('si', 'sales', 's', 's.id = si.sale_id')
            ->join('si', 'product', 'p', 'p.id = si.product_id')
            ->leftJoin('p', 'categories', 'c', 'c.id = p.category_id')
            ->andWhere("s.status = 'posted'")
            ->groupBy('p.id, p.name, c.name, p.unit')
            ->orderBy('value', 'DESC')
            ->addOrderBy('p.id', 'ASC');

        $this->applyAbcFilters($qb, $from, $to, $categoryId, $currency);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    private function applyAbcFilters(
        QueryBuilder $qb,
        ?string $from,
        ?string $to,
        ?int $categoryId,
        ?Currency $currency,
    ): void {
        if ($from !== null) {
            $qb->andWhere('s.doc_date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('s.doc_date < :to')->setParameter('to', $to);
        }
        if ($categoryId !== null) {
            $qb->andWhere('p.category_id = :category')->setParameter('category', $categoryId);
        }
        if ($currency !== null) {
            $qb->andWhere('si.currency = :currency')->setParameter('currency', $currency->value);
        }
    }
}
