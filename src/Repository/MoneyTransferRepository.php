<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MoneyTransfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MoneyTransfer|null find($id, $lockMode = null, $lockVersion = null)
 * @method MoneyTransfer|null findOneBy(array $criteria, array $orderBy = null)
 * @method MoneyTransfer[]    findAll()
 * @method MoneyTransfer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MoneyTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoneyTransfer::class);
    }

    public function findLastNumber(): ?string
    {
        $result = $this->createQueryBuilder('mt')
            ->select('mt.number')
            ->orderBy('mt.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }

    /**
     * @param MoneyTransfer[] $transfers
     */
    public function lockMoneyTransfers(array $transfers): void
    {
        $unique = [];
        foreach ($transfers as $transfer) {
            $unique[$transfer->getId()] = $transfer;
        }
        ksort($unique);

        foreach ($unique as $transfer) {
            $this->getEntityManager()->lock($transfer, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * Raw DBAL on purpose: it bypasses the identity map, so it sees what another
     * transaction has committed.
     */
    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM money_transfers WHERE id = :id',
            ['id' => $id]
        );
    }
}
