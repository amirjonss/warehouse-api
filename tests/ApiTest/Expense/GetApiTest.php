<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetExpenseCollection(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createExpense($client, '2026-08-25', '100.00');

        $response = $client->request(Request::METHOD_GET, '/api/expenses');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessFilterExpensesByDocDate(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createExpense($client, '2026-08-25', '100.00');
        $this->createExpense($client, '2026-07-01', '40.00');

        $response = $client->request(Request::METHOD_GET, '/api/expenses?docDate[after]=2026-08-01');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testIncorrectGetExpenseCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/expenses');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
