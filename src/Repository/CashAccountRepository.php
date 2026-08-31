<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Account\Enums\CashAccountKind;
use App\Component\Product\Enums\Currency;
use App\Entity\CashAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CashAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @method CashAccount|null findOneBy(array $criteria, array $orderBy = null)
 * @method CashAccount[]    findAll()
 * @method CashAccount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CashAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashAccount::class);
    }

    public function findByKindAndCurrency(CashAccountKind $kind, Currency $currency): ?CashAccount
    {
        return $this->findOneBy(['kind' => $kind, 'currency' => $currency]);
    }

    /**
     * Locks the given accounts (deduplicated, ascending by id) and re-reads the
     * denormalised balance under the lock, never trusting the in-memory copy.
     *
     * Accounts come LAST in the global lock order, which every write path follows:
     *
     *     documents (payments │ receipts │ sales │ money_transfers │ supplier_payments)
     *       -> sales / receipts
     *       -> clients / suppliers   (refresh: denormalised debt)
     *       -> cash_sessions         (refresh: denormalised balance)
     *       -> cash_accounts         (refresh: denormalised balance)
     *
     * Violating that order deadlocks in production, not in the tests.
     *
     * @param CashAccount[] $accounts
     */
    public function lockAccounts(array $accounts): void
    {
        $unique = [];
        foreach ($accounts as $account) {
            $unique[$account->getId()] = $account;
        }
        ksort($unique);

        foreach ($unique as $account) {
            $this->getEntityManager()->refresh($account, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * @return array<string, string> currency => total
     */
    public function getWalletTotals(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT currency, COALESCE(SUM(balance), 0) AS total FROM cash_accounts GROUP BY currency'
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['currency']] = (string) $row['total'];
        }

        return $totals;
    }
}
