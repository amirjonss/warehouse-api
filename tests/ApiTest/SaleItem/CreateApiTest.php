<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SaleItem;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateSaleItemAndRecalculateTotals(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('120.00', $item['total']);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(120.0, (float) $sale['totalUsd']);
    }

    /** Adding a line reserves stock straight away, before the sale is posted. */
    public function testSuccessCreateSaleItemAllocatesFromBatches(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $allocations = $this->saleItemAllocations($client, $item['@id']);

        // 90 units are still available in the oldest batch, so one layer covers it.
        $this->assertCount(1, $allocations);
        $this->assertSame(20.0, (float) $allocations[0]['quantity']);
        $this->assertSame('2.00', $allocations[0]['costPrice']);
    }

    /** With no price given, the product's list price for that currency is used. */
    public function testSuccessCreateSaleItemFallsBackToProductPrice(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $response = $client->request(Request::METHOD_POST, '/api/sale_items', [
            'body' => json_encode([
                'sale' => $saleIri,
                'product' => $this->productIri('Test Product USD 1'),
                'quantity' => '2.000',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // The fixture product lists at 5.00 USD.
        $this->assertSame('5.00', $response->toArray()['price']);
    }

    public function testIncorrectCreateSaleItemWithNonPositiveQuantity(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '0', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'quantity']]]);
    }

    /** Selling more than is on hand must be refused, not silently allowed to go negative. */
    public function testIncorrectCreateSaleItemBeyondAvailableStock(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $this->addSaleItem($client, $saleIri, 'Test Product No Stock', '5.000', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateDuplicateSaleItem(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '5.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '5.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateSaleItemOnPostedSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $postedIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $this->addSaleItem($client, $postedIri, 'Test Product USD 2', '1.000', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateSaleItemAnonymously(): void
    {
        $saleIri = $this->createDraftSale($this->createSalesClientWithCredentials());

        $this->addSaleItem($this->createAnonymousClient(), $saleIri, 'Test Product USD 1', '1.000', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
