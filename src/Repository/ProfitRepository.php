<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Product\Enums\Currency;
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
     * @return array<string, string> profit indexed by currency code
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

    /**
     * Per-product realised profit for the ABC analysis, ranked best first.
     *
     * Reversals live in the same table with a negative profit and type "reversed", so a
     * plain SUM already nets them out.
     *
     * The currency names the currency the goods were *sold* in, so that this metric selects
     * the same lines the revenue metric does. That needs a conversion: `profits.currency` is
     * the currency of the batch, not of the sale — SaleItemProfitCalculator works in the cost
     * currency, so selling a dollar batch for sums produces a profit row in dollars against
     * revenue in sums. The rate on the sale line bridges the two, and it is always present
     * here: SaleItemAllocationService refuses to post a cross-currency line without one.
     *
     * @param Currency    $currency the currency the goods were sold in
     * @param string|null $from     inclusive lower bound on occurred_at
     * @param string|null $to       exclusive upper bound on occurred_at
     *
     * @return array<int, array{id: int, name: string, category_name: string|null, unit: string|null, value: string}>
     */
    public function getAbcTotals(Currency $currency, ?string $from, ?string $to, ?int $categoryId): array
    {
        $value = <<<'SQL'
            SUM(CASE
                WHEN pr.currency = si.currency THEN pr.profit
                WHEN si.currency = 'USD' THEN pr.profit / si.rate
                ELSE pr.profit * si.rate
            END)
            SQL;

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select(
            'p.id AS id',
            'p.name AS name',
            'c.name AS category_name',
            'p.unit AS unit',
            $value . ' AS value',
        )
            ->from('profits', 'pr')
            ->join('pr', 'sale_items', 'si', 'si.id = pr.sale_item_id')
            ->join('pr', 'product', 'p', 'p.id = pr.product_id')
            ->leftJoin('p', 'categories', 'c', 'c.id = p.category_id')
            ->andWhere('si.currency = :currency')
            ->setParameter('currency', $currency->value)
            ->groupBy('p.id, p.name, c.name, p.unit')
            ->orderBy('value', 'DESC')
            ->addOrderBy('p.id', 'ASC');

        if ($from !== null) {
            $qb->andWhere('pr.occurred_at >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('pr.occurred_at < :to')->setParameter('to', $to);
        }
        if ($categoryId !== null) {
            $qb->andWhere('p.category_id = :category')->setParameter('category', $categoryId);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
