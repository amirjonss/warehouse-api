<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SaleItem;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateSaleItemPriceRecalculatesTotals(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['price' => '7.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['total' => '70.00']);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(70.0, (float) $sale['totalUsd']);
    }

    /** Changing the quantity has to re-run the FIFO allocation, not leave the old one. */
    public function testSuccessUpdateSaleItemQuantityReallocates(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['quantity' => '120.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $allocations = $this->saleItemAllocations($client, $item['@id']);

        // 120 no longer fits in the 90 left in the oldest batch, so it spills into the second.
        $this->assertCount(2, $allocations);
        $this->assertSame(120.0, array_sum(array_map(static fn (array $a): float => (float) $a['quantity'], $allocations)));
    }

    public function testIncorrectUpdateSaleItemBeyondAvailableStock(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['quantity' => '99999.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Lines of a posted sale are frozen: its ledgers were written against these numbers. */
    public function testIncorrectUpdateSaleItemOfPostedSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $items = $client->request(
            Request::METHOD_GET,
            '/api/sale_items?sale=' . basename($saleIri)
        )->toArray()['member'];

        $client->request(Request::METHOD_PATCH, $items[0]['@id'], [
            'body' => json_encode(['price' => '999.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectUpdateSaleItemAnonymously(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');

        $this->createAnonymousClient()->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['price' => '7.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
