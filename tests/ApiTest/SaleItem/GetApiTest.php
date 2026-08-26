<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SaleItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetSaleItemCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/sale_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // The single line of the posted fixture sale.
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessGetSaleItem(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $iri = $client->request(Request::METHOD_GET, '/api/sale_items')->toArray()['member'][0]['@id'];

        $response = $client->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $response->toArray();
        $this->assertSame(10.0, (float) $data['quantity']);
        $this->assertSame('5.00', $data['price']);
        $this->assertSame('50.00', $data['total']);
    }

    public function testIncorrectGetSaleItemCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/sale_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
