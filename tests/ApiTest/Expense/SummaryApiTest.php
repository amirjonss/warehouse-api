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
        $client = $this->createSalesClientWithCredentials();
        $this->createExpense($client, '2026-08-25', '100.00');
        $this->createExpense($client, '2026-08-26', '40.50');

        $response = $client->request(Request::METHOD_POST, '/api/expenses/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(140.5, (float) $response->toArray()['totalAmount']);
    }

    public function testSuccessGetExpenseSummaryWithoutExpensesIsZero(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/expenses/summary'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(0.0, (float) $response->toArray()['totalAmount']);
    }

    public function testIncorrectGetExpenseSummaryAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/expenses/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
