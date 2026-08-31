<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SupplierPayment|null find($id, $lockMode = null, $lockVersion = null)
 * @method SupplierPayment|null findOneBy(array $criteria, array $orderBy = null)
 * @method SupplierPayment[]    findAll()
 * @method SupplierPayment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SupplierPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierPayment::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('sp')
            ->select('sp.number')
            ->orderBy('sp.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * @param SupplierPayment[] $payments
     */
    public function lockSupplierPayments(array $payments): void
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

    /** Raw DBAL: it bypasses the identity map and sees what another transaction committed. */
    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM supplier_payments WHERE id = :id',
            ['id' => $id]
        );
    }
}
