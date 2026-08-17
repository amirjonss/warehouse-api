<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Sale|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sale|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sale[]    findAll()
 * @method Sale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.number')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * @param Sale[] $sales
     */
    public function lockSales(array $sales): void
    {
        $unique = [];
        foreach ($sales as $sale) {
            $unique[$sale->getId()] = $sale;
        }
        ksort($unique);

        foreach ($unique as $sale) {
            $this->getEntityManager()->lock($sale, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM sales WHERE id = :id',
            ['id' => $id]
        );
    }
}
