<?php

declare(strict_types=1);

namespace App\Tests\Integration\SupplierPayment;

use App\Component\Account\Enums\CashAccountKind;
use App\Component\Account\Exceptions\InsufficientAccountBalanceException;
use App\Component\Core\Enums\DocStatus;
use App\Component\Product\Enums\Currency;
use App\Component\SupplierPayment\Exceptions\InsufficientSupplierPaymentAmountException;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentStatusTransitionException;
use App\Component\SupplierPayment\SupplierPaymentFactory;
use App\DataFixtures\UserFixtures;
use App\Entity\CashAccount;
use App\Entity\Receipt;
use App\Entity\Supplier;
use App\Entity\SupplierPayment;
use App\Entity\SupplierPaymentAllocation;
use App\Entity\User;
use App\Repository\CashAccountRepository;
use App\Repository\ReceiptRepository;
use App\Repository\SupplierRepository;
use App\Repository\UserRepository;
use App\Service\CashAccountOpeningBalanceService;
use App\Service\SupplierPaymentChangeStatusService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Races over money owed to suppliers. As in CashConcurrencyTest, the "other request" is
 * raw SQL that has already committed while the entity in the identity map still holds
 * the old figures.
 */
class SupplierPayableConcurrencyTest extends KernelTestCase
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

    /** Two payouts against the same money: the second must hit the re-read balance. */
    public function testStaleAccountBalanceBlocksADoubleSupplierPayment(): void
    {
        $account = $this->fundedUsdCash('200.00');
        $accountId = $account->getId();

        $payment = $this->draftPaymentFor('RC-00001', '200.00', $account);
        $paymentId = $payment->getId();

        $this->em->clear();
        $payment = $this->em->find(SupplierPayment::class, $paymentId);
        $this->login($this->admin());

        $this->drainInParallel($accountId, '200.00');

        $this->expectException(InsufficientAccountBalanceException::class);

        try {
            $payment->setStatus(DocStatus::POSTED);
            static::getContainer()->get(SupplierPaymentChangeStatusService::class)->changeStatus($payment);
        } finally {
            $this->assertAccountJournalMatchesBalance($accountId);
        }
    }

    /** The receipt was settled by another request; paying it again must be refused. */
    public function testStalePayableBalanceBlocksPayingOneReceiptTwice(): void
    {
        $account = $this->fundedUsdCash('1000.00');
        $payment = $this->draftPaymentFor('RC-00001', '200.00', $account);
        $paymentId = $payment->getId();
        $receiptId = $this->receipt('RC-00001')->getId();

        $this->em->clear();
        $payment = $this->em->find(SupplierPayment::class, $paymentId);
        $this->login($this->admin());

        $this->settleInParallel($receiptId, '200.00');

        $this->expectException(InsufficientSupplierPaymentAmountException::class);

        $payment->setStatus(DocStatus::POSTED);
        static::getContainer()->get(SupplierPaymentChangeStatusService::class)->changeStatus($payment);
    }

    /** Posted while we waited for the lock: a second post would double every row. */
    public function testStaleStatusBlocksASecondPost(): void
    {
        $account = $this->fundedUsdCash('1000.00');
        $payment = $this->draftPaymentFor('RC-00001', '200.00', $account);
        $paymentId = $payment->getId();

        $this->em->clear();
        $payment = $this->em->find(SupplierPayment::class, $paymentId);
        $this->login($this->admin());

        $this->connection->executeStatement(
            "UPDATE supplier_payments SET status = 'posted', posted_at = NOW() WHERE id = :id",
            ['id' => $paymentId]
        );

        try {
            $payment->setStatus(DocStatus::POSTED);
            static::getContainer()->get(SupplierPaymentChangeStatusService::class)->changeStatus($payment);
            $this->fail('the supplier payment was posted twice off a stale status');
        } catch (SupplierPaymentStatusTransitionException) {
            // Expected.
        }

        $this->assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_debts WHERE supplier_payment_id = :id',
                ['id' => $paymentId]
            ),
            'the stale post must not have written any payable rows'
        );
    }

    /** The denormalised total on the supplier is only ever the sum of the journal. */
    public function testSupplierDebtJournalMatchesTheDenormalisedTotal(): void
    {
        $account = $this->fundedUsdCash('1000.00');
        $payment = $this->draftPaymentFor('RC-00001', '120.00', $account);

        $payment->setStatus(DocStatus::POSTED);
        static::getContainer()->get(SupplierPaymentChangeStatusService::class)->changeStatus($payment);

        $supplierId = $this->supplier()->getId();

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT s.debt_usd,
                       COALESCE((SELECT SUM(amount) FROM supplier_debts
                                  WHERE supplier_id = s.id AND currency = 'USD'), 0) AS journal
                  FROM suppliers s
                 WHERE s.id = :id
            SQL,
            ['id' => $supplierId]
        );

        $this->assertSame((float) $row['debt_usd'], (float) $row['journal']);
    }

    // ---------- fixtures ----------

    private function fundedUsdCash(string $amount): CashAccount
    {
        $account = static::getContainer()->get(CashAccountRepository::class)
            ->findByKindAndCurrency(CashAccountKind::CASH, Currency::USD);

        static::getContainer()->get(CashAccountOpeningBalanceService::class)
            ->set($account, $amount, null, $this->admin());

        return $account;
    }

    private function draftPaymentFor(string $receiptNumber, string $amount, CashAccount $account): SupplierPayment
    {
        $payment = static::getContainer()->get(SupplierPaymentFactory::class)->create(
            $this->admin(),
            $this->supplier(),
            $account,
            $amount,
            Currency::USD
        );
        $this->em->persist($payment);
        $this->em->flush();

        $allocation = new SupplierPaymentAllocation();
        $allocation
            ->setSupplierPayment($payment)
            ->setReceipt($this->receipt($receiptNumber))
            ->setCurrency(Currency::USD)
            ->setAmountSpent($amount)
            ->setAmountClosed($amount)
            ->setRoundingWriteOff('0.00');
        $payment->addAllocation($allocation);

        $this->em->persist($allocation);
        $this->em->flush();

        return $payment;
    }

    private function drainInParallel(int $accountId, string $amount): void
    {
        $this->connection->executeStatement(
            'UPDATE cash_accounts SET balance = balance - :amount WHERE id = :id',
            ['id' => $accountId, 'amount' => $amount]
        );
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO account_entries (account_id, occurred_at, kind, amount, created_by_id, note)
                VALUES (:id, NOW(), 'supplier_payment', :amount, :user, 'Параллельная оплата')
            SQL,
            ['id' => $accountId, 'amount' => '-' . $amount, 'user' => $this->admin()->getId()]
        );
    }

    /** The "other request" closed the whole payable of that receipt. */
    private function settleInParallel(int $receiptId, string $amount): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO supplier_debts
                    (supplier_id, receipt_id, created_by_id, occurred_at, amount, currency, doc_type)
                VALUES (:supplier, :receipt, :user, NOW(), :amount, 'USD', 'supplier_payment')
            SQL,
            [
                'supplier' => $this->supplier()->getId(),
                'receipt' => $receiptId,
                'user' => $this->admin()->getId(),
                'amount' => '-' . $amount,
            ]
        );
    }

    private function assertAccountJournalMatchesBalance(int $accountId): void
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

        $this->assertSame((float) $row['balance'], (float) $row['journal']);
    }

    private function receipt(string $number): Receipt
    {
        return static::getContainer()->get(ReceiptRepository::class)->findOneBy(['number' => $number]);
    }

    private function supplier(): Supplier
    {
        return static::getContainer()->get(SupplierRepository::class)->findOneBy(['name' => 'Test Supplier 1']);
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
