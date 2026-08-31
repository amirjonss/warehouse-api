<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashAccount;

use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the company holds. Money still in sellers' bags is not here — that is what
 * /cash_sessions/on_hands reports, and the two together are the whole picture.
 */
class WalletSummaryApiTest extends WalletTestCase
{
    public function testSuccessSummaryTotalsPerCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->fund($admin, $this->accountIri('cash', 'UZS'), '1000000.00');
        $this->fund($admin, $this->accountIri('bank', 'UZS'), '500000.00');
        $this->fund($admin, $this->accountIri('cash', 'USD'), '400.00');

        $summary = $this->summary($admin);

        $this->assertSame(400.0, (float) $summary['totalUsd']);
        $this->assertSame(1500000.0, (float) $summary['totalUzs']);
    }

    public function testSuccessSummaryListsEveryAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $summary = $this->summary($admin);

        $this->assertCount(4, $summary['accounts']);
        $names = array_column($summary['accounts'], 'name');
        $this->assertContains('Наличные UZS', $names);
        $this->assertContains('Счёт / банк', $names);
    }

    public function testIncorrectSummaryAsSales(): void
    {
        $sales = $this->createSalesClientWithCredentials();

        $sales->request(Request::METHOD_POST, '/api/cash_accounts/summary', ['body' => json_encode([])]);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }

    private function summary(\ApiPlatform\Symfony\Bundle\Test\Client $admin): array
    {
        return $admin->request(Request::METHOD_POST, '/api/cash_accounts/summary', [
            'body' => json_encode([]),
        ])->toArray();
    }
}
