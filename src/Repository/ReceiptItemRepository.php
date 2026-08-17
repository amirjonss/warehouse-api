<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReceiptItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ReceiptItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceiptItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceiptItem[]    findAll()
 * @method ReceiptItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceiptItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReceiptItem::class);
    }
}
