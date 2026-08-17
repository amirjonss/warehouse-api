<?php

namespace App\Repository;

use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Batch|null find($id, $lockMode = null, $lockVersion = null)
 * @method Batch|null findOneBy(array $criteria, array $orderBy = null)
 * @method Batch[]    findAll()
 * @method Batch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Batch::class);
    }

    public function findLastNumberForProduct(Product $product): ?string
    {
        $result = $this->createQueryBuilder('b')
            ->select('b.number')
            ->andWhere('b.product = :product')
            ->setParameter('product', $product)
            ->orderBy('b.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    public function isUsed(Batch $batch): bool
    {
        return bccomp($this->computeLiveRemainingQty($batch), $batch->getInitialQty(), 3) !== 0;
    }

    public function computeLiveRemainingQty(Batch $batch): string
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(sm.quantity), 0)')
            ->from(StockMovement::class, 'sm')
            ->andWhere('sm.batch = :batch')
            ->setParameter('batch', $batch)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $result;
    }

    public function lockBatches(array $batches): void
    {
        $unique = [];
        foreach ($batches as $batch) {
            $unique[$batch->getId()] = $batch;
        }
        ksort($unique);

        foreach ($unique as $batch) {
            $this->getEntityManager()->refresh($batch, LockMode::PESSIMISTIC_WRITE);
        }
    }

}
