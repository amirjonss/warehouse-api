<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\MoneyTransfer;

use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One document covers both moves the business actually makes: depositing cash at the
 * bank, where the currency does not change and the sums must match, and exchanging sums
 * for dollars, where the agreed rate explains why they do not.
 */
class CreateApiTest extends WalletTestCase
{
    public function testSuccessCreateDraftCollection(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $iri = $this->createDraftTransfer(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '10000000.00'
        );

        $transfer = $admin->request(Request::METHOD_GET, $iri)->toArray();
        $this->assertSame('draft', $transfer['status']);
        $this->assertSame('MT-00001', $transfer['number']);
        $this->assertNull($transfer['rate']);
        $this->assertSame(10000000.0, (float) $transfer['amountReceived']);
    }

    public function testSuccessCreateDraftExchange(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $iri = $this->createDraftTransfer(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('cash', 'USD'),
            '12000000.00',
            '1000.00',
            '12000'
        );

        $transfer = $admin->request(Request::METHOD_GET, $iri)->toArray();
        $this->assertSame(1000.0, (float) $transfer['amountReceived']);
        $this->assertSame(12000.0, (float) $transfer['rate']);
    }

    public function testIncorrectSameAccountOnBothSides(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');

        $this->attemptCreate($admin, $cashIri, $cashIri, '100.00', '100.00', null);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('должны отличаться', $this->detail($admin));
    }

    public function testIncorrectRateOnASameCurrencyTransfer(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->attemptCreate(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '100.00',
            '100.00',
            '12000'
        );

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('не требует курса', $this->detail($admin));
    }

    public function testIncorrectMissingRateOnACrossCurrencyTransfer(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->attemptCreate(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('cash', 'USD'),
            '12000000.00',
            '1000.00',
            null
        );

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('укажите курс', $this->detail($admin));
    }

    /** No commission on a deposit, so a difference within one currency is a typo. */
    public function testIncorrectAmountReceivedDiffersFromAmountSentInTheSameCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->attemptCreate(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '10000000.00',
            '9990000.00',
            null
        );

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('суммы должны совпадать', $this->detail($admin));
    }

    public function testIncorrectAmountReceivedContradictsTheRate(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        // 12 000 000 at 12 000 is 1 000, not 100: a mistyped digit.
        $this->attemptCreate(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('cash', 'USD'),
            '12000000.00',
            '100.00',
            '12000'
        );

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('проверьте курс или суммы', $this->detail($admin));
    }

    public function testIncorrectNonPositiveAmount(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->attemptCreate(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '0',
            '0',
            null
        );

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectCreateAsSales(): void
    {
        $sales = $this->createSalesClientWithCredentials();

        $this->attemptCreate(
            $sales,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '100.00',
            '100.00',
            null
        );

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }

    private function attemptCreate(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $from,
        string $to,
        string $amountSent,
        string $amountReceived,
        ?string $rate
    ): void {
        $client->request(Request::METHOD_POST, '/api/money_transfers', [
            'body' => json_encode([
                'docDate' => '2026-08-20',
                'fromAccount' => $from,
                'toAccount' => $to,
                'amountSent' => $amountSent,
                'amountReceived' => $amountReceived,
                'rate' => $rate,
            ]),
        ]);
    }
}
