<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ExchangeRate;

use App\DataFixtures\ExchangeRateFixtures;
use App\Entity\ExchangeRate;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteExchangeRate(): void
    {
        $iri = $this->findIriBy(ExchangeRate::class, ['rateSell' => ExchangeRateFixtures::RATE_SELL]);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectDeleteExchangeRateAnonymously(): void
    {
        $iri = $this->findIriBy(ExchangeRate::class, ['rateSell' => ExchangeRateFixtures::RATE_SELL]);

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
