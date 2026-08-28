<?php

declare(strict_types=1);

namespace App\Tests\Integration\Cash;

use App\Component\Cash\Exceptions\CashSessionClosedException;
use App\Component\Cash\Exceptions\SessionAlreadyOpenException;
use App\Component\Cash\Exceptions\SessionNumberTakenException;
use App\Component\Cash\Exceptions\InsufficientCashException;
use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Expense\ExpenseFactory;
use App\Component\Payment\PaymentFactory;
use App\Component\Product\Enums\Currency;
use App\DataFixtures\UserFixtures;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\CashCollectService;
use App\Service\CashExpenseService;
use App\Service\CashSessionCloseService;
use App\Service\CashSessionOpenService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Races over a session's money.
 *
 * None of this is reproducible over HTTP: the kernel reboots between requests, so the
 * second copy of the entity never survives long enough to reach the lock. The "concurrent
 * request" is therefore simulated with raw SQL while the entity already sits in the
 * identity map — exactly the state a second worker is in once it has waited out a
 * SELECT ... FOR UPDATE.
 *
 * What is under test is the whole point of the lock: the balance is re-read rather than
 * taken from a stale copy.
 */
class CashConcurrencyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();

        $this->login($this->seller());
    }

    /**
     * Two expenses against the same money. The first already went through in a concurrent
     * request; the second has to hit the re-read balance, or the journal drifts away from
     * the balance and the money ends up spent twice.
     */
    public function testStaleBalanceIsRefreshedUnderTheLock(): void
    {
        $session = $this->openSessionWithCash('100.00', Currency::USD);
        $sessionId = $session->getId();

        // The entity in the identity map still holds 100.00, just like the second worker.
        $this->em->clear();
        $this->em->find(CashSession::class, $sessionId);
        $this->login($this->seller());

        $this->spendInParallel($sessionId, '100.00');

        $expense = static::getContainer()->get(ExpenseFactory::class)
            ->create($this->seller(), 'Second expense of the same money', '100.00', Currency::USD);

        $this->expectException(InsufficientCashException::class);

        try {
            static::getContainer()->get(CashExpenseService::class)->create($expense);
        } finally {
            $this->assertJournalMatchesBalance($sessionId);
        }
    }

    /**
     * The session was closed while we waited for the lock. Without re-checking the status
     * the second request writes another set of closing rows against the same money.
     */
    public function testStaleStatusBlocksASecondClose(): void
    {
        $session = $this->openSessionWithCash('100.00', Currency::USD);
        $sessionId = $session->getId();

        $this->em->clear();
        $session = $this->em->find(CashSession::class, $sessionId);
        $admin = $this->admin();

        $this->closeInParallel($sessionId, '100.00', $admin->getId());

        try {
            static::getContainer()->get(CashSessionCloseService::class)
                ->close($session, '100.00', '0', null, $admin);
            $this->fail('the session was closed twice off a stale status');
        } catch (CashSessionClosedException) {
            // Expected.
        }

        $this->assertJournalMatchesBalance($sessionId);
        $this->assertSame(
            1,
            (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM cash_entries WHERE session_id = :id AND kind = 'handover'",
                ['id' => $sessionId]
            ),
            'the closing handover must be recorded exactly once'
        );
    }

    /**
     * The table has two unique indexes and they must not be confused. A number taken by a
     * concurrent request means "try again", not "you already have an open session": the
     * latter sends the seller looking for a session that does not exist.
     */
    public function testNumberCollisionIsNotReportedAsAnOpenSession(): void
    {
        // Numbers are derived from the newest session by id, so plant a row whose number is
        // higher than the newer one's: the next number will already be taken.
        $this->insertClosedSession('CS-00002');
        $this->insertClosedSession('CS-00001');

        $seller = $this->seller();

        try {
            static::getContainer()->get(CashSessionOpenService::class)->open($seller, $seller);
            $this->fail('a duplicate number went through without an error');
        } catch (SessionAlreadyOpenException $e) {
            $this->fail('a number uniqueness violation was reported as an open session: ' . $e->getMessage());
        } catch (SessionNumberTakenException $e) {
            $this->assertStringContainsString('CS-00002', $e->getMessage());
            $this->assertStringContainsString('повторите', $e->getMessage());
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    /**
     * A pessimistic lock needs a transaction. It used to be the caller's job to open one,
     * and every new caller ran into TransactionRequiredException; now the service opens it
     * itself.
     */
    public function testCollectWorksOutsideAnAmbientTransaction(): void
    {
        $this->assertFalse(
            $this->connection->isTransactionActive() && $this->connection->getTransactionNestingLevel() > 1,
            'the test must start without a transaction of its own on top of the DAMA wrapper'
        );

        $session = $this->openSessionWithCash('100.00', Currency::USD);

        $this->assertSame(100.0, (float) $this->connection->fetchOne(
            'SELECT balance_usd FROM cash_sessions WHERE id = :id',
            ['id' => $session->getId()]
        ));
        $this->assertJournalMatchesBalance($session->getId());
    }

    // ---------- fixtures ----------

    private function insertClosedSession(string $number): void
    {
        $adminId = $this->admin()->getId();

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO cash_sessions
                    (user_id, opened_by_id, number, status, opened_at, closed_at, closed_by_id,
                     balance_usd, balance_uzs, unconfirmed_usd, unconfirmed_uzs)
                VALUES (:user, :user, :number, 'closed', NOW(), NOW(), :user, 0, 0, 0, 0)
            SQL,
            ['user' => $adminId, 'number' => $number]
        );
    }

    private function openSessionWithCash(string $amount, Currency $currency): CashSession
    {
        $seller = $this->seller();
        $session = static::getContainer()->get(CashSessionOpenService::class)->open($seller, $seller);

        $payment = static::getContainer()->get(PaymentFactory::class)->create(
            $seller,
            static::getContainer()->get(ClientRepository::class)->findOneBy([]),
            $amount,
            $currency,
            PaymentMethod::CASH,
            null,
            null
        );
        $payment->setStatus(DocStatus::POSTED);
        $this->em->persist($payment);
        $this->em->flush();

        static::getContainer()->get(CashCollectService::class)->record($payment);

        return $session;
    }

    /** The "other request" spent all of the session's cash and has already committed. */
    private function spendInParallel(int $sessionId, string $amount): void
    {
        $this->connection->executeStatement(
            'UPDATE cash_sessions SET balance_usd = balance_usd - :amount WHERE id = :id',
            ['id' => $sessionId, 'amount' => $amount]
        );
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO cash_entries (session_id, occurred_at, kind, amount, currency, status, created_by_id)
                VALUES (:id, NOW(), 'expense', :amount, 'USD', 'confirmed', :user)
            SQL,
            ['id' => $sessionId, 'amount' => '-' . $amount, 'user' => $this->seller()->getId()]
        );
    }

    /** The "other admin" closed the session outright: handover row, zeroed balance, status. */
    private function closeInParallel(int $sessionId, string $amount, int $adminId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO cash_entries (session_id, occurred_at, kind, amount, currency, status, created_by_id, note)
                VALUES (:id, NOW(), 'handover', :amount, 'USD', 'confirmed', :user, 'Сдача при закрытии смены')
            SQL,
            ['id' => $sessionId, 'amount' => '-' . $amount, 'user' => $adminId]
        );
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE cash_sessions
                   SET status = 'closed', closed_at = NOW(), closed_by_id = :user, balance_usd = '0.00'
                 WHERE id = :id
            SQL,
            ['id' => $sessionId, 'user' => $adminId]
        );
    }

    /**
     * The journal is the source of truth. Computed with raw SQL: once the service's
     * transaction rolls back the EntityManager is closed, while the connection still works.
     */
    private function assertJournalMatchesBalance(int $sessionId): void
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    cs.balance_usd,
                    cs.balance_uzs,
                    COALESCE((SELECT SUM(amount) FROM cash_entries
                               WHERE session_id = cs.id AND currency = 'USD'), 0) AS journal_usd,
                    COALESCE((SELECT SUM(amount) FROM cash_entries
                               WHERE session_id = cs.id AND currency = 'UZS'), 0) AS journal_uzs
                  FROM cash_sessions cs
                 WHERE cs.id = :id
            SQL,
            ['id' => $sessionId]
        );

        $this->assertSame(
            (float) $row['balance_usd'],
            (float) $row['journal_usd'],
            'USD: the journal drifted away from the session\'s denormalised balance'
        );
        $this->assertSame(
            (float) $row['balance_uzs'],
            (float) $row['journal_uzs'],
            'UZS: the journal drifted away from the session\'s denormalised balance'
        );
    }

    private function login(User $user): void
    {
        static::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function seller(): User
    {
        return $this->userByEmail(UserFixtures::SALES_EMAIL);
    }

    private function admin(): User
    {
        return $this->userByEmail(UserFixtures::ADMIN_EMAIL);
    }

    private function userByEmail(string $email): User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }
}
