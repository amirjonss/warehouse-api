<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\StockMovement;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** The movement log is append-only and is the source of truth behind remainingQty. */
class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetStockMovementCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/stock_movements');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // Four incoming movements from the fixture receipts, one outgoing from the fixture sale.
        $this->assertSame(5, $response->toArray()['totalItems']);
    }

    public function testSuccessMovementsOfOneProductSumToItsRemainingQty(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');

        $movements = $client->request(
            Request::METHOD_GET,
            '/api/stock_movements?product=' . basename($productIri)
        )->toArray()['member'];

        $sum = 0.0;
        foreach ($movements as $movement) {
            $sum += (float) $movement['quantity'];
        }

        $product = $client->request(Request::METHOD_GET, $productIri)->toArray();
        $this->assertSame((float) $product['remainingQty'], $sum);
    }

    public function testSuccessOutgoingMovementIsNegative(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/stock_movements?type=out'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        foreach ($response->toArray()['member'] as $movement) {
            $this->assertLessThan(0, (float) $movement['quantity']);
        }
    }

    public function testIncorrectGetStockMovementCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/stock_movements');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
