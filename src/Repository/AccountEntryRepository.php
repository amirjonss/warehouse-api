<?php

declare(strict_types=1);

namespace App\Repository;

use App\Component\Account\Enums\AccountEntryKind;
use App\Entity\AccountEntry;
use App\Entity\CashAccount;
use App\Entity\MoneyTransfer;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AccountEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccountEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccountEntry[]    findAll()
 * @method AccountEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountEntry::class);
    }

    /**
     * @return AccountEntry[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->findBy(['payment' => $payment], ['id' => 'ASC']);
    }

    /**
     * @return AccountEntry[]
     */
    public function findByMoneyTransfer(MoneyTransfer $moneyTransfer): array
    {
        return $this->findBy(['moneyTransfer' => $moneyTransfer], ['id' => 'ASC']);
    }

    public function hasOpening(CashAccount $account): bool
    {
        return $this->findOneBy([
            'account' => $account,
            'kind' => AccountEntryKind::OPENING,
        ]) !== null;
    }

    /**
     * What the journal says the account holds. The denormalised balance is reconciled
     * against this, the same way cash_entries reconcile a session.
     */
    public function getJournalTotal(CashAccount $account): string
    {
        return (string) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE account_id = :id',
            ['id' => $account->getId()]
        );
    }
}
