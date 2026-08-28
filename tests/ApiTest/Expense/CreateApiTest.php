<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateExpense(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/expenses',
            ['body' => json_encode(['docDate' => '2026-08-25', 'description' => 'Fuel', 'amount' => '100.00', 'currency' => 'UZS'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('Fuel', $data['description']);
        $this->assertSame('100.00', $data['amount']);
        $this->assertSame('UZS', $data['currency']);
        // createdBy comes from the token, not the payload.
        $this->assertSame('sales', $data['createdBy']['firstName']);
    }

    public function testIncorrectCreateExpenseWithNonPositiveAmount(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/expenses',
            ['body' => json_encode(['docDate' => '2026-08-25', 'description' => 'Fuel', 'amount' => '-5.00', 'currency' => 'UZS'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'amount']]]);
    }

    public function testIncorrectCreateExpenseAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/expenses',
            ['body' => json_encode(['docDate' => '2026-08-25', 'description' => 'Fuel', 'amount' => '100.00', 'currency' => 'UZS'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
