<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ExchangeRate;

use App\DataFixtures\ExchangeRateFixtures;
use App\Entity\ExchangeRate;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetExchangeRateCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/exchange_rates');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessGetExchangeRateItem(): void
    {
        $iri = $this->findIriBy(ExchangeRate::class, ['rateSell' => ExchangeRateFixtures::RATE_SELL]);
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains([
            '@id' => $iri,
            'rateBuy' => ExchangeRateFixtures::RATE_BUY,
            'rateSell' => ExchangeRateFixtures::RATE_SELL,
        ]);
    }

    public function testIncorrectGetExchangeRateCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/exchange_rates');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
