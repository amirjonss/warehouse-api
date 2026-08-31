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
        $client = $this->createAdminClientWithCredentials();
        $account = $this->accountIri('cash', 'UZS');
        $this->fund($client, $account, '1000000.00');
        $this->createExpense($client, '2026-08-25', '100.00', 'Test expense', 'UZS', $account);

        $response = $client->request(Request::METHOD_GET, '/api/expenses');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessFilterExpensesByDocDate(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $account = $this->accountIri('cash', 'UZS');
        $this->fund($client, $account, '1000000.00');
        $this->createExpense($client, '2026-08-25', '100.00', 'Test expense', 'UZS', $account);
        $this->createExpense($client, '2026-07-01', '40.00', 'Test expense', 'UZS', $account);

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
