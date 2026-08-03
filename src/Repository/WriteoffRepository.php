<?php

namespace App\Repository;

use App\Entity\Writeoff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Writeoff|null find($id, $lockMode = null, $lockVersion = null)
 * @method Writeoff|null findOneBy(array $criteria, array $orderBy = null)
 * @method Writeoff[]    findAll()
 * @method Writeoff[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WriteoffRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Writeoff::class);
    }
}
