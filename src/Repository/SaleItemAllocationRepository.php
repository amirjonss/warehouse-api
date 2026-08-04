<?php

namespace App\Repository;

use App\Entity\SaleItemAllocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SaleItemAllocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method SaleItemAllocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method SaleItemAllocation[]    findAll()
 * @method SaleItemAllocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaleItemAllocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItemAllocation::class);
    }
}
