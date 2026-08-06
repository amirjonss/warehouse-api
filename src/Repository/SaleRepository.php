<?php

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
     * Locks the given sales (deduplicated, ascending by id) with SELECT ... FOR UPDATE so a
     * concurrent payment can't read/close the same debt balance before this one commits.
     * Always lock through this method — locking in any other order can deadlock two
     * transactions against each other.
     *
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

    /**
     * Reads the status directly from the DB (bypassing the identity map, which already holds
     * this request's pending change), so it reflects whatever the last committed writer set —
     * call after lockSales() to detect a concurrent change_status on the same sale.
     */
    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM sales WHERE id = :id',
            ['id' => $id]
        );
    }
}
