<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Core\Enums\DocStatus;
use App\Entity\PaymentAllocation;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PaymentAllocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentAllocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentAllocation[]    findAll()
 * @method PaymentAllocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentAllocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentAllocation::class);
    }

    public function hasPostedAllocationForSale(Sale $sale): bool
    {
        $count = $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->join('pa.payment', 'p')
            ->andWhere('pa.sale = :sale')
            ->andWhere('p.status = :status')
            ->setParameter('sale', $sale)
            ->setParameter('status', DocStatus::POSTED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
