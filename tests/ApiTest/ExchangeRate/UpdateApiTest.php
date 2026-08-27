<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ExchangeRate;

use App\DataFixtures\ExchangeRateFixtures;
use App\Entity\ExchangeRate;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateExchangeRate(): void
    {
        $iri = $this->findIriBy(ExchangeRate::class, ['rateSell' => ExchangeRateFixtures::RATE_SELL]);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['rateSell' => 12750]),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'rateSell' => 12750]);
    }

    /** The buy/sell invariant is enforced on updates too, not just on create. */
    public function testIncorrectUpdateExchangeRateBelowBuyRate(): void
    {
        $iri = $this->findIriBy(ExchangeRate::class, ['rateSell' => ExchangeRateFixtures::RATE_SELL]);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['rateSell' => 100]),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
