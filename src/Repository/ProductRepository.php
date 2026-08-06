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
