<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Profit;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetProfitCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/profits');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $profits = $response->toArray()['member'];

        // One realized entry: 10 units sold at 5.00 against a 2.00 cost layer.
        $this->assertCount(1, $profits);
        $this->assertSame(30.0, (float) $profits[0]['profit']);
        $this->assertSame('realized', $profits[0]['type']);
    }

    /** Margins are admin-only knowledge. */
    public function testIncorrectGetProfitCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/profits');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectGetProfitCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/profits');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
