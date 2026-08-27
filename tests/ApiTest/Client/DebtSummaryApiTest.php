<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/clients/summary is a read-only report exposed as a POST operation
 * (input: false, read: false), which is why it answers 201 rather than 200.
 */
class DebtSummaryApiTest extends BaseApiTestCase
{
    public function testSuccessGetDebtSummary(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/clients/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        // Only "Test Client 2" owes anything, and exactly the posted fixture sale total.
        $this->assertSame(1, $data['count']);
        $this->assertSame('50.00', $data['totalDebtUsd']);
        $this->assertSame('0.00', $data['totalDebtUzs']);
    }

    public function testIncorrectGetDebtSummaryAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/clients/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
