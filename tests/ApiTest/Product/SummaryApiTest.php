<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SummaryApiTest extends BaseApiTestCase
{
    public function testSuccessGetStockSummary(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/products/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        // "positions" counts products that actually have stock on hand (remainingQty > 0):
        // the three received fixture products. "Test Product No Stock" was never received.
        $this->assertSame(3, $data['positions']);
        $this->assertSame(1, $data['outOfStock']);
        $this->assertArrayHasKey('low', $data);
    }

    /** Soft-deleted products are hidden from the collection, so they must not be counted either. */
    public function testSoftDeletedProductsAreExcludedFromTheSummary(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $before = $client->request(Request::METHOD_POST, '/api/products/summary')->toArray();
        $this->assertSame(1, $before['outOfStock']);

        $client->request(Request::METHOD_DELETE, $this->productIri('Test Product No Stock'));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $after = $client->request(Request::METHOD_POST, '/api/products/summary')->toArray();
        $this->assertSame(0, $after['outOfStock']);
        $this->assertSame($before['positions'], $after['positions']);
    }

    public function testIncorrectGetStockSummaryAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/products/summary');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
