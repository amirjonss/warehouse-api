<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierPaymentAllocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SupplierPaymentAllocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method SupplierPaymentAllocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method SupplierPaymentAllocation[]    findAll()
 * @method SupplierPaymentAllocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SupplierPaymentAllocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierPaymentAllocation::class);
    }
}
