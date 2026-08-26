<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Profit;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SummaryApiTest extends BaseApiTestCase
{
    public function testSuccessGetProfitSummary(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/profits/summary?from=2020-01-01&to=2030-01-01'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(30.0, (float) $response->toArray()['totalUsd']);
    }

    public function testSuccessGetProfitSummaryOutsideThePeriodIsZero(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/profits/summary?from=2000-01-01&to=2000-12-31'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(0.0, (float) $response->toArray()['totalUsd']);
    }

    public function testIncorrectGetProfitSummaryByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/profits/summary?from=2020-01-01&to=2030-01-01'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
