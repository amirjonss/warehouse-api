<?php

namespace App\Repository;

use App\Component\Core\Enums\DocStatus;
use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\SaleItem;
use App\Entity\WriteoffItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $entityManager = $this->getEntityManager();

        $saleItemsCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(si.id)')
            ->from(SaleItem::class, 'si')
            ->andWhere('si.batch = :batch')
            ->setParameter('batch', $batch)
            ->getQuery()
            ->getSingleScalarResult();

        if ($saleItemsCount > 0) {
            return true;
        }

        $writeoffItemsCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(wi.id)')
            ->from(WriteoffItem::class, 'wi')
            ->andWhere('wi.batch = :batch')
            ->setParameter('batch', $batch)
            ->getQuery()
            ->getSingleScalarResult();

        return $writeoffItemsCount > 0;
    }

    public function getRemainingQty(Batch $batch): string
    {
        $entityManager = $this->getEntityManager();

        $soldQty = $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(si.quantity), 0)')
            ->from(SaleItem::class, 'si')
            ->innerJoin('si.sale', 'sale')
            ->andWhere('si.batch = :batch')
            ->andWhere('sale.status = :posted')
            ->setParameter('batch', $batch)
            ->setParameter('posted', DocStatus::POSTED)
            ->getQuery()
            ->getSingleScalarResult();

        $writtenOffQty = $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(wi.quantity), 0)')
            ->from(WriteoffItem::class, 'wi')
            ->innerJoin('wi.writeoff', 'writeoff')
            ->andWhere('wi.batch = :batch')
            ->andWhere('writeoff.status = :posted')
            ->setParameter('batch', $batch)
            ->setParameter('posted', DocStatus::POSTED)
            ->getQuery()
            ->getSingleScalarResult();

        return bcsub(bcsub($batch->getInitialQty(), (string) $soldQty, 3), (string) $writtenOffQty, 3);
    }
}
