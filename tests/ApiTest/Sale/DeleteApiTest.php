<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Sale;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteDraftSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $client->request(Request::METHOD_DELETE, $saleIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $saleIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** A posted sale owns stock, profit and debt entries, so deleting it is refused. */
    public function testIncorrectDeletePostedSale(): void
    {
        $iri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Deleting a draft with lines releases the stock the lines had reserved. */
    public function testSuccessDeleteDraftSaleWithItemsReleasesAllocations(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $before = $client->request(Request::METHOD_GET, '/api/sale_item_allocations')->toArray()['totalItems'];

        $saleIri = $this->createDraftSale($client);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '5.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request(Request::METHOD_DELETE, $saleIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $after = $client->request(Request::METHOD_GET, '/api/sale_item_allocations')->toArray()['totalItems'];
        $this->assertSame($before, $after);
    }

    public function testIncorrectDeleteSaleAnonymously(): void
    {
        $iri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
