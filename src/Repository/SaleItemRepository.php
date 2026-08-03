<?php

namespace App\Repository;

use App\Entity\SaleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SaleItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method SaleItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method SaleItem[]    findAll()
 * @method SaleItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItem::class);
    }
}
