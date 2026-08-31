<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Product\Enums\Currency;
use App\Entity\Receipt;
use App\Entity\Supplier;
use App\Entity\SupplierDebt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SupplierDebt|null find($id, $lockMode = null, $lockVersion = null)
 * @method SupplierDebt|null findOneBy(array $criteria, array $orderBy = null)
 * @method SupplierDebt[]    findAll()
 * @method SupplierDebt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SupplierDebtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierDebt::class);
    }

    /**
     * @return array<string, string> balance indexed by currency code
     */
    public function getBalanceForReceipt(Receipt $receipt): array
    {
        $rows = $this->createQueryBuilder('sd')
            ->select('sd.currency AS currency, SUM(sd.amount) AS total')
            ->andWhere('sd.receipt = :receipt')
            ->setParameter('receipt', $receipt)
            ->groupBy('sd.currency')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $currency = $row['currency'] instanceof Currency ? $row['currency']->value : (string) $row['currency'];
            $result[$currency] = (string) $row['total'];
        }

        return $result;
    }

    /**
     * Outstanding payables for several receipts in a single query.
     *
     * @param int[] $receiptIds
     * @return array<int, array<string, string>> receiptId => [currency code => balance]
     */
    public function getBalancesForReceipts(array $receiptIds): array
    {
        if ($receiptIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('sd')
            ->select('IDENTITY(sd.receipt) AS receiptId', 'sd.currency AS currency', 'SUM(sd.amount) AS total')
            ->andWhere('sd.receipt IN (:ids)')
            ->setParameter('ids', $receiptIds)
            ->groupBy('sd.receipt', 'sd.currency')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $currency = $row['currency'] instanceof Currency ? $row['currency']->value : (string) $row['currency'];
            $result[(int) $row['receiptId']][$currency] = (string) $row['total'];
        }

        return $result;
    }

    /**
     * A supplier's unpaid receipts in the given currency, oldest first — what
     * auto-allocation walks through.
     *
     * @return array<int, array{receipt: Receipt, balance: string}>
     */
    public function getOutstandingReceiptsForSupplier(Supplier $supplier, Currency $currency): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('r', 'SUM(sd.amount) AS balance')
            ->from(Receipt::class, 'r')
            ->join(SupplierDebt::class, 'sd', 'WITH', 'sd.receipt = r')
            ->andWhere('sd.supplier = :supplier')
            ->andWhere('sd.currency = :currency')
            ->setParameter('supplier', $supplier)
            ->setParameter('currency', $currency)
            ->groupBy('r.id')
            ->having('SUM(sd.amount) > 0')
            ->orderBy('r.docDate', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['receipt' => $row[0], 'balance' => (string) $row['balance']];
        }

        return $result;
    }

    /** Has anything at all been paid against this receipt? Cancelling it would strand that. */
    public function isPaid(Receipt $receipt): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM supplier_debts WHERE receipt_id = :id AND supplier_payment_id IS NOT NULL)',
            ['id' => $receipt->getId()]
        );
    }
}
