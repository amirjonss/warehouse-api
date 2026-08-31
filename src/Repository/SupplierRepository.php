<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Supplier|null find($id, $lockMode = null, $lockVersion = null)
 * @method Supplier|null findOneBy(array $criteria, array $orderBy = null)
 * @method Supplier[]    findAll()
 * @method Supplier[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    /**
     * Locks the given suppliers (deduplicated, ascending by id) and re-reads their
     * denormalised payables under the lock. Suppliers come before cash accounts in the
     * global lock order — see CashAccountRepository::lockAccounts().
     *
     * @param Supplier[] $suppliers
     */
    public function lockSuppliers(array $suppliers): void
    {
        $unique = [];
        foreach ($suppliers as $supplier) {
            $unique[$supplier->getId()] = $supplier;
        }
        ksort($unique);

        foreach ($unique as $supplier) {
            $this->getEntityManager()->refresh($supplier, LockMode::PESSIMISTIC_WRITE);
        }
    }
}
