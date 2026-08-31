<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashAccount;

use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The balance an account starts life with, counted from the safe once. A partial unique
 * index makes it a one-shot, so nobody can quietly conjure money into the treasury later.
 */
class OpeningBalanceApiTest extends WalletTestCase
{
    public function testSuccessOpeningBalanceCreditsTheAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');

        $this->fund($admin, $accountIri, '12000000.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->assertSame(12000000.0, (float) $this->accountBalance($admin, $accountIri));
    }

    public function testSuccessOpeningBalanceCreatesAJournalRow(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('bank', 'UZS');

        $this->fund($admin, $accountIri, '500000.00');

        $entries = $this->accountEntries($admin, $accountIri);
        $this->assertCount(1, $entries);
        $this->assertSame('opening', $entries[0]['kind']);
        $this->assertSame(500000.0, (float) $entries[0]['amount']);
        $this->assertAccountJournalMatchesBalance($admin, $accountIri);
    }

    public function testIncorrectSecondOpeningBalanceForTheSameAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('card', 'UZS');

        $this->fund($admin, $accountIri, '100000.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->fund($admin, $accountIri, '999.00');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('уже введён', $this->detail($admin));

        // The second attempt changed nothing.
        $this->assertSame(100000.0, (float) $this->accountBalance($admin, $accountIri));
    }

    public function testIncorrectNegativeOpeningBalance(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->fund($admin, $this->accountIri('cash', 'USD'), '-5.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectZeroOpeningBalance(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->fund($admin, $this->accountIri('cash', 'USD'), '0');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectNonNumericAmount(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        // Without validation ahead of the service this would blow up inside bcmath as a 500.
        $admin->request(Request::METHOD_POST, $this->accountIri('cash', 'USD') . '/opening_balance', [
            'body' => json_encode(['amount' => 'сто', 'note' => null]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectOpeningBalanceAsSales(): void
    {
        $sales = $this->createSalesClientWithCredentials();

        $sales->request(Request::METHOD_POST, $this->accountIri('cash', 'UZS') . '/opening_balance', [
            'body' => json_encode(['amount' => '100.00']),
        ]);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }
}
