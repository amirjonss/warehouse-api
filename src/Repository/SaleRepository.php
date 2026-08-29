<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Product\Enums\Currency;
use App\Component\Sale\Enums\SalesInterval;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Sale|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sale|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sale[]    findAll()
 * @method Sale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaleRepository extends ServiceEntityRepository
{
    /** Realised profit expressed in the currency the sale was made in. See getAnalysis(). */
    private const PROFIT_IN_SALE_CURRENCY = <<<'SQL'
        CASE
            WHEN pr.currency = si.currency THEN pr.profit
            WHEN si.currency = 'USD' THEN pr.profit / si.rate
            ELSE pr.profit * si.rate
        END
        SQL;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.number')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * @param Sale[] $sales
     */
    public function lockSales(array $sales): void
    {
        $unique = [];
        foreach ($sales as $sale) {
            $unique[$sale->getId()] = $sale;
        }
        ksort($unique);

        foreach ($unique as $sale) {
            $this->getEntityManager()->lock($sale, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM sales WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Sales dynamics: documents, quantity, revenue, cost and profit per interval.
     *
     * Cost is derived as revenue minus profit rather than recomputed from the FIFO
     * allocations. Two reasons: the profit journal is already the system's own answer, so the
     * report can never disagree with a sale's own card; and `sale_item_allocations.cost_rate`
     * is 1 whenever the cost is in the same currency as the receipt, which is a "no
     * conversion needed" marker rather than a rate — dividing by it produces nonsense.
     *
     * The currency is mandatory: the report covers the sales made in it, and nothing is
     * summed across the two. Note that a sale with lines in both currencies is counted as a
     * document in both reports, so the two document counts must not be added together.
     *
     * Profit needs converting even so, because `profits.currency` is the currency of the
     * batch, not of the sale: SaleItemProfitCalculator works in the cost currency. Sell a
     * dollar batch for sums and the profit row comes out in dollars while the revenue is in
     * sums. The rate on the sale line is exactly what bridges them, and it is guaranteed to
     * be there — SaleItemAllocationService refuses to post such a line without one.
     *
     * @param string|null $from inclusive lower bound on doc_date (YYYY-MM-DD)
     * @param string|null $to   exclusive upper bound on doc_date (YYYY-MM-DD)
     *
     * @return array<int, array{period: string, documents: int, quantity: string, revenue: string, profit: string}>
     */
    public function getAnalysis(
        SalesInterval $interval,
        ?string $from,
        ?string $to,
        ?int $categoryId,
        Currency $currency,
    ): array {
        // The unit comes from a closed enum, never from the request: date_trunc takes it as
        // a literal and there is no way to bind it as a parameter.
        $unit = $interval->value;

        $revenue = 'SUM(si.total)';
        $profit = 'SUM(' . self::PROFIT_IN_SALE_CURRENCY . ')';

        $filters = ' AND si.currency = :currency';
        $params = ['currency' => $currency->value];
        if ($from !== null) {
            $filters .= ' AND s.doc_date >= :from';
            $params['from'] = $from;
        }
        if ($to !== null) {
            $filters .= ' AND s.doc_date < :to';
            $params['to'] = $to;
        }
        if ($categoryId !== null) {
            $filters .= ' AND p.category_id = :category';
            $params['category'] = $categoryId;
        }

        // Revenue and profit are aggregated apart and joined by period: a sale line has many
        // FIFO allocations, so joining them in one pass would multiply the revenue by their
        // count.
        $sql = <<<SQL
            WITH revenue AS (
                SELECT date_trunc('{$unit}', s.doc_date)::date AS period,
                       COUNT(DISTINCT s.id) AS documents,
                       SUM(si.quantity) AS quantity,
                       {$revenue} AS revenue
                  FROM sale_items si
                  JOIN sales s ON s.id = si.sale_id
                  JOIN product p ON p.id = si.product_id
                 WHERE s.status = 'posted'{$filters}
                 GROUP BY 1
            ), profit AS (
                SELECT date_trunc('{$unit}', s.doc_date)::date AS period,
                       {$profit} AS profit
                  FROM profits pr
                  JOIN sale_items si ON si.id = pr.sale_item_id
                  JOIN sales s ON s.id = si.sale_id
                  JOIN product p ON p.id = si.product_id
                 WHERE s.status = 'posted'{$filters}
                 GROUP BY 1
            )
            SELECT r.period, r.documents, r.quantity, r.revenue,
                   COALESCE(pf.profit, 0) AS profit
              FROM revenue r
              LEFT JOIN profit pf ON pf.period = r.period
             ORDER BY r.period
            SQL;

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);
    }
}
