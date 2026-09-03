<?php

declare(strict_types=1);

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

    /**
     * A product's batches in FIFO order, oldest first, each with its live remaining quantity.
     *
     * This is the queue a stocktake adjusts: a shortage is taken from the front, a surplus is
     * put back at the front. Quantities are summed from the journal, not read off the cached
     * column, because the caller is about to decide how much each batch can absorb.
     *
     * @return array<int, array{batch: Batch, remainingQty: string}>
     */
    public function findFifoQueue(Product $product): array
    {
        /** @var Batch[] $batches */
        $batches = $this->createQueryBuilder('b')
            ->andWhere('b.product = :product')
            ->setParameter('product', $product)
            ->orderBy('b.receivedAt', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        if ($batches === []) {
            return [];
        }

        $sums = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT batch_id, SUM(quantity)::text FROM stock_movements WHERE product_id = :product GROUP BY batch_id',
            ['product' => $product->getId()]
        );

        return array_map(
            static fn (Batch $batch): array => [
                'batch' => $batch,
                'remainingQty' => (string) ($sums[$batch->getId()] ?? '0'),
            ],
            $batches
        );
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
