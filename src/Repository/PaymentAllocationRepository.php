<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Core\Enums\DocStatus;
use App\Entity\Payment;
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

    /**
     * The distinct posted payments that have an allocation closing the given sale, so a sale
     * cancellation can unwind them automatically.
     *
     * @return Payment[]
     */
    public function findPostedPaymentsForSale(Sale $sale): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->distinct()
            ->from(Payment::class, 'p')
            ->innerJoin('p.allocations', 'pa')
            ->andWhere('pa.sale = :sale')
            ->andWhere('p.status = :status')
            ->setParameter('sale', $sale)
            ->setParameter('status', DocStatus::POSTED)
            ->getQuery()
            ->getResult();
    }
}
