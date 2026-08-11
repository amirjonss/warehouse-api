<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Locks the given products (deduplicated, ascending by id) with SELECT ... FOR UPDATE so
     * two concurrent receipts for the same product can't generate the same batch number.
     * Always lock through this method — locking in any other order can deadlock two
     * transactions against each other.
     *
     * @param Product[] $products
     */
    public function lockProducts(array $products): void
    {
        $unique = [];
        foreach ($products as $product) {
            $unique[$product->getId()] = $product;
        }
        ksort($unique);

        foreach ($unique as $product) {
            $this->getEntityManager()->lock($product, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * Counts products in stock (remaining > 0) and low-on-stock (remaining <= min_stock,
     * including out-of-stock) directly off the denormalized remaining_qty column, so the
     * dashboard/stock summary tiles don't need to fetch and sum every product on the frontend.
     *
     * @return array{positions: int, low: int, outOfStock: int}
     */
    public function getStockSummary(): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE remaining_qty > 0) AS positions,
                COUNT(*) FILTER (WHERE remaining_qty <= min_stock) AS low,
                COUNT(*) FILTER (WHERE remaining_qty <= 0) AS out_of_stock
            FROM product
            SQL;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql);

        return [
            'positions' => (int) $row['positions'],
            'low' => (int) $row['low'],
            'outOfStock' => (int) $row['out_of_stock'],
        ];
    }

//    /**
//     * @return Product[] Returns an array of Product objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Product
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
