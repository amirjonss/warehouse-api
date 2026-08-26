<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyApiTest extends BaseApiTestCase
{
    public function testSuccessGetDailyExpenses(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createExpense($client, '2026-08-25', '100.00');
        $this->createExpense($client, '2026-08-25', '20.00');
        $this->createExpense($client, '2026-08-26', '40.00');

        $response = $client->request(Request::METHOD_POST, '/api/expenses/daily');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $items = $response->toArray()['items'];

        // Two distinct days, with same-day expenses rolled up.
        $this->assertCount(2, $items);
    }

    public function testSuccessGetDailyExpensesWithoutExpensesIsEmpty(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/expenses/daily'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame([], $response->toArray()['items']);
    }

    public function testIncorrectGetDailyExpensesAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/expenses/daily');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
