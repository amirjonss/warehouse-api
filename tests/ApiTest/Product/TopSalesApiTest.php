<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Arguments come from the query string: this operation has input: false. */
class TopSalesApiTest extends BaseApiTestCase
{
    public function testSuccessGetTopSales(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products/top-sales?from=2020-01-01&to=2030-01-01&limit=6'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $items = $response->toArray()['items'];

        // Only the fixture sale exists, so exactly one product has ever been sold.
        $this->assertCount(1, $items);
        $this->assertSame('Test Product USD 1', $items[0]['productName']);
        $this->assertSame('10.000', $items[0]['quantity']);
        $this->assertSame('50.00', $items[0]['totalUsd']);
    }

    public function testSuccessGetTopSalesOutsideThePeriodIsEmpty(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products/top-sales?from=2000-01-01&to=2000-12-31&limit=6'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame([], $response->toArray()['items']);
    }

    public function testIncorrectGetTopSalesAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/products/top-sales?from=2020-01-01&to=2030-01-01'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
