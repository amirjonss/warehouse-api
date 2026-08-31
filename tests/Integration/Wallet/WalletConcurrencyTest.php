<?php

declare(strict_types=1);

namespace App\Tests\Integration\Wallet;

use App\Component\Account\Enums\CashAccountKind;
use App\Component\Account\Exceptions\InsufficientAccountBalanceException;
use App\Component\Account\Exceptions\OpeningBalanceAlreadySetException;
use App\Component\Core\Enums\DocStatus;
use App\Component\MoneyTransfer\Exceptions\MoneyTransferStatusTransitionException;
use App\Component\MoneyTransfer\MoneyTransferFactory;
use App\Component\Product\Enums\Currency;
use App\DataFixtures\UserFixtures;
use App\Entity\CashAccount;
use App\Entity\MoneyTransfer;
use App\Entity\User;
use App\Repository\CashAccountRepository;
use App\Repository\UserRepository;
use App\Service\CashAccountOpeningBalanceService;
use App\Service\MoneyTransferChangeStatusService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Races over the treasury, in the same shape as CashConcurrencyTest: the "other request"
 * is raw SQL that has already committed, while the entity in the identity map still holds
 * the old figures — exactly the state a second worker is in once it has waited out a
 * SELECT ... FOR UPDATE.
 */
class WalletConcurrencyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();

        $this->login($this->admin());
    }

    /**
     * Two payouts against the same money. The first already went through in a concurrent
     * request; the second must hit the re-read balance, or the account goes negative and
     * the journal drifts away from it.
     */
    public function testStaleAccountBalanceIsRefreshedUnderTheLock(): void
    {
        $cash = $this->fundedAccount(CashAccountKind::CASH, Currency::UZS, '1000000.00');
        $bank = $this->account(CashAccountKind::BANK, Currency::UZS);

        $transfer = $this->draftTransfer($cash, $bank, '1000000.00');
        $transferId = $transfer->getId();
        $accountId = $cash->getId();

        // The entity in the identity map still holds the full balance.
        $this->em->clear();
        $transfer = $this->em->find(MoneyTransfer::class, $transferId);
        $this->login($this->admin());

        $this->drainInParallel($accountId, '1000000.00');

        $this->expectException(InsufficientAccountBalanceException::class);

        try {
            $transfer->setStatus(DocStatus::POSTED);
            static::getContainer()->get(MoneyTransferChangeStatusService::class)->changeStatus($transfer);
        } finally {
            $this->assertJournalMatchesBalance($accountId);
        }
    }

    /**
     * The transfer was posted while we waited for the lock. Without re-checking the status
     * the second request writes a second pair of journal rows against the same money.
     */
    public function testStaleStatusBlocksASecondTransferPost(): void
    {
        $cash = $this->fundedAccount(CashAccountKind::CASH, Currency::UZS, '1000000.00');
        $bank = $this->account(CashAccountKind::BANK, Currency::UZS);

        $transfer = $this->draftTransfer($cash, $bank, '400000.00');
        $transferId = $transfer->getId();
        $cashId = $cash->getId();
        $bankId = $bank->getId();

        $this->em->clear();
        $transfer = $this->em->find(MoneyTransfer::class, $transferId);
        $this->login($this->admin());

        $this->postInParallel($transferId, $cashId, $bankId, '400000.00');

        try {
            $transfer->setStatus(DocStatus::POSTED);
            static::getContainer()->get(MoneyTransferChangeStatusService::class)->changeStatus($transfer);
            $this->fail('the transfer was posted twice off a stale status');
        } catch (MoneyTransferStatusTransitionException) {
            // Expected.
        }

        $this->assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM account_entries WHERE money_transfer_id = :id AND account_id = :account',
                ['id' => $transferId, 'account' => $bankId]
            ),
            'the receiving leg must be recorded exactly once'
        );
        $this->assertJournalMatchesBalance($cashId);
        $this->assertJournalMatchesBalance($bankId);
    }

    /**
     * The in-PHP "has an opening balance already?" check races two concurrent requests;
     * the partial unique index does not.
     */
    public function testOpeningBalanceRaceIsRejectedByThePartialUniqueIndex(): void
    {
        $account = $this->account(CashAccountKind::CARD, Currency::UZS);
        $accountId = $account->getId();

        // The other request slipped its opening row in and committed.
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO account_entries (account_id, occurred_at, kind, amount, created_by_id, note)
                VALUES (:id, NOW(), 'opening', '500000.00', :user, 'Параллельный ввод')
            SQL,
            ['id' => $accountId, 'user' => $this->admin()->getId()]
        );
        $this->connection->executeStatement(
            'UPDATE cash_accounts SET balance = balance + 500000 WHERE id = :id',
            ['id' => $accountId]
        );

        $this->expectException(OpeningBalanceAlreadySetException::class);

        try {
            static::getContainer()->get(CashAccountOpeningBalanceService::class)
                ->set($account, '111.00', null, $this->admin());
        } finally {
            $this->assertSame(
                1,
                (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM account_entries WHERE account_id = :id AND kind = 'opening'",
                    ['id' => $accountId]
                )
            );
        }
    }

    /**
     * Two transfers between the same pair of accounts in opposite directions. Both lock
     * the same two rows, and lockAccounts() ksorts by id, so the order is identical in
     * both and neither can deadlock waiting on the other.
     */
    public function testTwoTransfersBetweenTheSamePairDoNotDeadlock(): void
    {
        $cash = $this->fundedAccount(CashAccountKind::CASH, Currency::UZS, '1000000.00');
        $bank = $this->account(CashAccountKind::BANK, Currency::UZS);

        $there = $this->draftTransfer($cash, $bank, '600000.00');
        $there->setStatus(DocStatus::POSTED);
        static::getContainer()->get(MoneyTransferChangeStatusService::class)->changeStatus($there);

        $back = $this->draftTransfer($bank, $cash, '600000.00');
        $back->setStatus(DocStatus::POSTED);
        static::getContainer()->get(MoneyTransferChangeStatusService::class)->changeStatus($back);

        $this->assertSame(1000000.0, (float) $this->connection->fetchOne(
            'SELECT balance FROM cash_accounts WHERE id = :id',
            ['id' => $cash->getId()]
        ));
        $this->assertSame(0.0, (float) $this->connection->fetchOne(
            'SELECT balance FROM cash_accounts WHERE id = :id',
            ['id' => $bank->getId()]
        ));
        $this->assertJournalMatchesBalance($cash->getId());
        $this->assertJournalMatchesBalance($bank->getId());
    }

    // ---------- fixtures ----------

    private function account(CashAccountKind $kind, Currency $currency): CashAccount
    {
        return static::getContainer()->get(CashAccountRepository::class)
            ->findByKindAndCurrency($kind, $currency);
    }

    private function fundedAccount(CashAccountKind $kind, Currency $currency, string $amount): CashAccount
    {
        $account = $this->account($kind, $currency);

        static::getContainer()->get(CashAccountOpeningBalanceService::class)
            ->set($account, $amount, null, $this->admin());

        return $account;
    }

    private function draftTransfer(CashAccount $from, CashAccount $to, string $amount): MoneyTransfer
    {
        $transfer = static::getContainer()->get(MoneyTransferFactory::class)->create(
            $this->admin(),
            $from,
            $to,
            $amount,
            $amount,
            null
        );

        $this->em->persist($transfer);
        $this->em->flush();

        return $transfer;
    }

    /** The "other request" moved every last sum off the account and has already committed. */
    private function drainInParallel(int $accountId, string $amount): void
    {
        $this->connection->executeStatement(
            'UPDATE cash_accounts SET balance = balance - :amount WHERE id = :id',
            ['id' => $accountId, 'amount' => $amount]
        );
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO account_entries (account_id, occurred_at, kind, amount, created_by_id, note)
                VALUES (:id, NOW(), 'transfer_out', :amount, :user, 'Параллельное списание')
            SQL,
            ['id' => $accountId, 'amount' => '-' . $amount, 'user' => $this->admin()->getId()]
        );
    }

    /** The "other admin" posted the very same transfer outright. */
    private function postInParallel(int $transferId, int $fromId, int $toId, string $amount): void
    {
        $userId = $this->admin()->getId();

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO account_entries (account_id, occurred_at, kind, amount, created_by_id, money_transfer_id)
                VALUES (:id, NOW(), 'transfer_out', :amount, :user, :transfer)
            SQL,
            ['id' => $fromId, 'amount' => '-' . $amount, 'user' => $userId, 'transfer' => $transferId]
        );
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO account_entries (account_id, occurred_at, kind, amount, created_by_id, money_transfer_id)
                VALUES (:id, NOW(), 'transfer_in', :amount, :user, :transfer)
            SQL,
            ['id' => $toId, 'amount' => $amount, 'user' => $userId, 'transfer' => $transferId]
        );
        $this->connection->executeStatement(
            'UPDATE cash_accounts SET balance = balance - :amount WHERE id = :id',
            ['id' => $fromId, 'amount' => $amount]
        );
        $this->connection->executeStatement(
            'UPDATE cash_accounts SET balance = balance + :amount WHERE id = :id',
            ['id' => $toId, 'amount' => $amount]
        );
        $this->connection->executeStatement(
            "UPDATE money_transfers SET status = 'posted', posted_at = NOW() WHERE id = :id",
            ['id' => $transferId]
        );
    }

    /**
     * Raw SQL on purpose: once the service's transaction rolls back the EntityManager is
     * closed, while the connection still works.
     */
    private function assertJournalMatchesBalance(int $accountId): void
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT ca.balance,
                       COALESCE((SELECT SUM(amount) FROM account_entries WHERE account_id = ca.id), 0) AS journal
                  FROM cash_accounts ca
                 WHERE ca.id = :id
            SQL,
            ['id' => $accountId]
        );

        $this->assertSame(
            (float) $row['balance'],
            (float) $row['journal'],
            'the journal drifted away from the account\'s denormalised balance'
        );
    }

    private function login(User $user): void
    {
        static::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function admin(): User
    {
        return static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);
    }
}
