<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\StockMovement;
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

    public function findOneActiveByName(string $name): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name = :name')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The product's remaining quantity summed from the movement journal rather than read off
     * the denormalised column, which is only ever as fresh as the last posting.
     */
    public function computeLiveRemainingQty(Product $product): string
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(sm.quantity), 0)')
            ->from(StockMovement::class, 'sm')
            ->andWhere('sm.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $result;
    }

    public function lockProducts(array $products): void
    {
        $unique = [];
        foreach ($products as $product) {
            $unique[$product->getId()] = $product;
        }
        ksort($unique);

        foreach ($unique as $product) {
            $this->getEntityManager()->refresh($product, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getStockSummary(): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE remaining_qty > 0) AS positions,
                COUNT(*) FILTER (WHERE remaining_qty <= min_stock) AS low,
                COUNT(*) FILTER (WHERE remaining_qty <= 0) AS out_of_stock
            FROM product
            WHERE deleted_at IS NULL
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
