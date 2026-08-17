<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WriteoffItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WriteoffItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method WriteoffItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method WriteoffItem[]    findAll()
 * @method WriteoffItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WriteoffItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WriteoffItem::class);
    }
}
