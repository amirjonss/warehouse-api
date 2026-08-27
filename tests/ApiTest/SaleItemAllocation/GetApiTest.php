<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SaleItemAllocation;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetAllocationCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // The single FIFO layer consumed by the posted fixture sale.
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    /** Purchase cost is admin-only: a sales user must not be able to see the margin. */
    public function testCostFieldsAreHiddenFromSalesRole(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $allocation = $response->toArray()['member'][0];

        $this->assertArrayHasKey('quantity', $allocation);
        $this->assertArrayNotHasKey('costPrice', $allocation);
        $this->assertArrayNotHasKey('costCurrency', $allocation);
        $this->assertArrayNotHasKey('costRate', $allocation);
    }

    public function testCostFieldsAreVisibleToAdmin(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $allocation = $response->toArray()['member'][0];

        $this->assertSame('2.00', $allocation['costPrice']);
        $this->assertSame('USD', $allocation['costCurrency']);
    }

    public function testSuccessFilterAllocationsBySaleItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Two layers for this line, and the fixture sale's layer is filtered out.
        $allocations = $this->saleItemAllocations($client, $item['@id']);
        $this->assertCount(2, $allocations);
        $this->assertSame(120.0, array_sum(array_map(
            static fn (array $a): float => (float) $a['quantity'],
            $allocations
        )));
    }

    public function testSuccessFilterAllocationsBySale(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '5.000', '9.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // One layer per line, and nothing from the fixture sale.
        $this->assertCount(2, $this->saleAllocations($client, $saleIri));
    }

    public function testSuccessFilterAllocationsByBatch(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $allocations = $client->request(Request::METHOD_GET, '/api/sale_item_allocations')->toArray()['member'];
        $batchIri = $allocations[0]['batch'];

        $response = $client->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations?batch=' . basename((string) $batchIri)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testIncorrectGetAllocationCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/sale_item_allocations');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
