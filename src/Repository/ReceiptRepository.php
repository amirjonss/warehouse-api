<?php

namespace App\Repository;

use App\Entity\Receipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Receipt|null find($id, $lockMode = null, $lockVersion = null)
 * @method Receipt|null findOneBy(array $criteria, array $orderBy = null)
 * @method Receipt[]    findAll()
 * @method Receipt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Receipt::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.number')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * Locks the given receipts (deduplicated, ascending by id) with SELECT ... FOR UPDATE so a
     * concurrent request can't process the same status transition twice. Always lock through
     * this method — locking in any other order can deadlock two transactions against each other.
     *
     * @param Receipt[] $receipts
     */
    public function lockReceipts(array $receipts): void
    {
        $unique = [];
        foreach ($receipts as $receipt) {
            $unique[$receipt->getId()] = $receipt;
        }
        ksort($unique);

        foreach ($unique as $receipt) {
            $this->getEntityManager()->lock($receipt, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * Reads the status directly from the DB (bypassing the identity map, which already holds
     * this request's pending change) — call after lockReceipts() to detect a concurrent
     * change_status on the same receipt.
     */
    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM receipts WHERE id = :id',
            ['id' => $id]
        );
    }
}
