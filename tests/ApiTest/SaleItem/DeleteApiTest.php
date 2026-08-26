<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SaleItem;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteSaleItemReleasesAllocationsAndTotals(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $first = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '2.000', '9.00');

        $client->request(Request::METHOD_DELETE, $first['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Only the second line is left: 2 * 9.00
        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(18.0, (float) $sale['totalUsd']);

        // The deleted line took its FIFO layers with it (orphanRemoval), so only the
        // fixture sale's allocation and the surviving line's allocation are left.
        $client->request(Request::METHOD_GET, $first['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A posted sale already wrote stock, profit and debt entries against this line, so it
     * may not be removed — the sale has to be cancelled instead.
     */
    public function testIncorrectDeleteSaleItemOfPostedSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $items = $client->request(
            Request::METHOD_GET,
            '/api/sale_items?sale=' . basename($saleIri)
        )->toArray()['member'];
        $this->assertCount(1, $items);

        $client->request(Request::METHOD_DELETE, $items[0]['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteSaleItemAnonymously(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '6.00');

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $item['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
