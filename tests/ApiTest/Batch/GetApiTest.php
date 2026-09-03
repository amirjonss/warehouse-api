<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Batch;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetBatchCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/batches');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // Four batches were created by posting the three fixture receipts.
        $this->assertSame(4, $response->toArray()['totalItems']);
    }

    /**
     * Batch numbers restart per product, so "Test Product USD 1" owns both B-0001 and
     * B-0002 — the two FIFO layers the allocation tests rely on.
     */
    public function testSuccessGetBatchesOfOneProductInFifoOrder(): void
    {
        $productIri = $this->productIri('Test Product USD 1');

        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $batches = $response->toArray()['member'];

        $this->assertCount(2, $batches);
        $this->assertSame('2.00', $batches[0]['purchasePrice']);
        $this->assertSame('2.50', $batches[1]['purchasePrice']);
        // 100 received, 10 already sold by the fixture sale.
        $this->assertSame(90.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(50.0, (float) $batches[1]['remainingQty']);
    }

    public function testSuccessGetBatchItem(): void
    {
        $productIri = $this->productIri('Test Product USD 1');
        $batches = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];

        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $batches[0]['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $batches[0]['@id'], 'number' => 'B-0001']);
    }

    /** A seller has to see the batches to count them during a stocktake. */
    public function testSuccessSalesCanListBatches(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/batches');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(4, $response->toArray()['totalItems']);
    }

    /** What we paid the supplier is not the seller's business, so it is hidden per property. */
    public function testSuccessPurchasePriceIsHiddenFromSales(): void
    {
        $productIri = $this->productIri('Test Product USD 1');
        $uri = '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc';

        $forSales = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $uri)
            ->toArray()['member'][0];
        $forAdmin = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $uri)
            ->toArray()['member'][0];

        $this->assertArrayNotHasKey('purchasePrice', $forSales);
        $this->assertArrayNotHasKey('rateSell', $forSales);
        // The seller still gets everything the count needs.
        $this->assertSame('B-0001', $forSales['number']);
        $this->assertSame(90.0, (float) $forSales['remainingQty']);

        $this->assertSame('2.00', $forAdmin['purchasePrice']);
    }

    public function testIncorrectGetBatchCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/batches');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
