<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Writeoff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
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

    /**
     * Locks the given writeoffs (deduplicated, ascending by id) with SELECT ... FOR UPDATE so a
     * concurrent request can't process the same status transition twice. Always lock through
     * this method — locking in any other order can deadlock two transactions against each other.
     *
     * @param Writeoff[] $writeoffs
     */
    public function lockWriteoffs(array $writeoffs): void
    {
        $unique = [];
        foreach ($writeoffs as $writeoff) {
            $unique[$writeoff->getId()] = $writeoff;
        }
        ksort($unique);

        foreach ($unique as $writeoff) {
            $this->getEntityManager()->lock($writeoff, LockMode::PESSIMISTIC_WRITE);
        }
    }

    public function getCurrentStatus(int $id): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT status FROM writeoffs WHERE id = :id',
            ['id' => $id]
        );
    }
}
