<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.number')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * Locks the given payments (deduplicated, ascending by id) with SELECT ... FOR UPDATE so a
     * concurrent request can't process the same status transition twice. Always lock through
     * this method — locking in any other order can deadlock two transactions against each other.
     *
     * @param Payment[] $payments
     */
    public function lockPayments(array $payments): void
    {
        $unique = [];
        foreach ($payments as $payment) {
            $unique[$payment->getId()] = $payment;
        }
        ksort($unique);

        foreach ($unique as $payment) {
            $this->getEntityManager()->lock($payment, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * Reads the status directly from the DB (bypassing the identity map, which already holds
     * this request's pending change) — call after lockPayments() to detect a concurrent
     * change_status on the same payment.
     */
    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM payments WHERE id = :id',
            ['id' => $id]
        );
    }
}
