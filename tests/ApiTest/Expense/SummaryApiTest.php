<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SummaryApiTest extends BaseApiTestCase
{
    public function testSuccessGetExpenseSummary(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $account = $this->accountIri('cash', 'UZS');
        $this->fund($client, $account, '1000000.00');
        $usdAccount = $this->accountIri('cash', 'USD');
        $this->fund($client, $usdAccount, '1000.00');
        $this->createExpense($client, '2026-08-25', '100.00', 'Test expense', 'UZS', $account);
        $this->createExpense($client, '2026-08-26', '40.50', 'Test expense', 'UZS', $account);
        $this->createExpense($client, '2026-08-26', '7.00', 'Test expense', 'USD', $usdAccount);

        $response = $client->request(Request::METHOD_POST, '/api/expenses/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // Currencies never mix: an expense now carries its own, and each is totalled apart.
        $data = $response->toArray();
        $this->assertSame(140.5, (float) $data['totalUzs']);
        $this->assertSame(7.0, (float) $data['totalUsd']);
    }

    public function testSuccessGetExpenseSummaryWithoutExpensesIsZero(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/expenses/summary'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();
        $this->assertSame(0.0, (float) $data['totalUzs']);
        $this->assertSame(0.0, (float) $data['totalUsd']);
    }

    public function testIncorrectGetExpenseSummaryAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/expenses/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
