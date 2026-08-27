<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ExchangeRate;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateExchangeRate(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/exchange_rates',
            ['body' => json_encode(['rateBuy' => 12600, 'rateSell' => 12800])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains(['rateBuy' => 12600, 'rateSell' => 12800]);
    }

    /** createdAt/createdBy are filled in by WriteSubscriber, not by the client. */
    public function testCreatedByIsFilledFromTheAuthenticatedUser(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/exchange_rates',
            ['body' => json_encode(['rateBuy' => 12600, 'rateSell' => 12800])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();
        $this->assertSame('sales', $data['createdBy']['firstName']);
        $this->assertNotEmpty($data['createdAt']);
    }

    public function testIncorrectCreateExchangeRateWithSellBelowBuy(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/exchange_rates',
            ['body' => json_encode(['rateBuy' => 13000, 'rateSell' => 12000])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains([
            'violations' => [['message' => 'rate_sell must not be lower than rate_buy']],
        ]);
    }

    public function testIncorrectCreateExchangeRateAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/exchange_rates',
            ['body' => json_encode(['rateBuy' => 12600, 'rateSell' => 12800])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
