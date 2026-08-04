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

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('w')
            ->select('w.number')
            ->orderBy('w.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }
}
