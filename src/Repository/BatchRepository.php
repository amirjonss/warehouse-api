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

    /**
     * Fresh SUM straight from the stock_movements ledger — the authoritative value for
     * concurrency-safe checks. Batch::getRemainingQty() (denormalized column, kept in
     * sync by StockMovementFactory) is fine for display, but callers gating a write
     * against "how much is actually left" must call this after lockBatches() so they
     * see any concurrently-committed change, not a value cached on this PHP object.
     */
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

    /**
     * Locks the given batches (deduplicated, ascending by id) with SELECT ... FOR UPDATE
     * so a concurrent transaction can't read/consume the same remaining quantity before
     * this one commits. Callers must always lock through this method — locking in any
     * other order can deadlock two transactions against each other.
     *
     * @param Batch[] $batches
     */
    public function lockBatches(array $batches): void
    {
        $unique = [];
        foreach ($batches as $batch) {
            $unique[$batch->getId()] = $batch;
        }
        ksort($unique);

        foreach ($unique as $batch) {
            $this->getEntityManager()->lock($batch, LockMode::PESSIMISTIC_WRITE);
        }
    }

}
