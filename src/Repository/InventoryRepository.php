<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Inventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Inventory|null find($id, $lockMode = null, $lockVersion = null)
 * @method Inventory|null findOneBy(array $criteria, array $orderBy = null)
 * @method Inventory[]    findAll()
 * @method Inventory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('i')
            ->select('i.number')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * Locks the given inventories (deduplicated, ascending by id) with SELECT ... FOR UPDATE so a
     * concurrent request can't process the same status transition twice. Always lock through
     * this method — locking in any other order can deadlock two transactions against each other.
     *
     * @param Inventory[] $inventories
     */
    public function lockInventories(array $inventories): void
    {
        $unique = [];
        foreach ($inventories as $inventory) {
            $unique[$inventory->getId()] = $inventory;
        }
        ksort($unique);

        foreach ($unique as $inventory) {
            $this->getEntityManager()->lock($inventory, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM inventories WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * The products a count sheet should cover, with their live ledger quantity.
     *
     * The remainder is summed from stock_movements rather than read off Product.remainingQty:
     * the cached column is only ever as fresh as the last posting, and a snapshot that starts
     * out stale would produce a fake discrepancy. Products already on the sheet are excluded,
     * so calling fill twice is safe and never touches a count somebody has already entered.
     *
     * Only products that were ever received are offered — a stocktake adjusts existing batches,
     * and a product without any has nothing to adjust.
     *
     * @return array<int, array{product_id: int, expected_qty: string}>
     */
    public function findCountableProductRows(
        Inventory $inventory,
        ?int $categoryId,
        bool $includeZeroStock,
        int $limit
    ): array {
        $sql = <<<'SQL'
            SELECT p.id AS product_id, COALESCE(sm.qty, 0)::text AS expected_qty
            FROM product p
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS qty FROM stock_movements GROUP BY product_id
            ) sm ON sm.product_id = p.id
            WHERE p.deleted_at IS NULL
              AND EXISTS (SELECT 1 FROM batches b WHERE b.product_id = p.id)
              AND (:category::int IS NULL OR p.category_id = :category::int)
              AND (:includeZeroStock OR COALESCE(sm.qty, 0) > 0)
              AND NOT EXISTS (
                  SELECT 1 FROM inventory_items ii
                  WHERE ii.inventory_id = :inventory AND ii.product_id = p.id
              )
            ORDER BY p.name
            LIMIT :limit
            SQL;

        // Types are spelled out: Postgres will not take an untyped false for a boolean, and
        // a null category has to arrive as a real NULL for the "no filter" branch to fire.
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            $sql,
            [
                'category' => $categoryId,
                'includeZeroStock' => $includeZeroStock,
                'inventory' => $inventory->getId(),
                'limit' => $limit,
            ],
            [
                'category' => ParameterType::INTEGER,
                'includeZeroStock' => ParameterType::BOOLEAN,
                'inventory' => ParameterType::INTEGER,
                'limit' => ParameterType::INTEGER,
            ]
        );
    }
}
