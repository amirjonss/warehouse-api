<?php

namespace App\Repository;

use App\Entity\PaymentAllocation;
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
}
