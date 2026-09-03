<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\InventoryItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteInventoryItem(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);
        $item = $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', 'B-0002', '50.000');

        $client->request(Request::METHOD_DELETE, $item['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, $this->inventoryItems($client, $inventoryIri));
    }

    public function testIncorrectDeleteItemOfPostedInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $item = $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '50.000');
        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $client = $this->createSalesClientWithCredentials();
        $client->request(Request::METHOD_DELETE, $item['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $client->request(Request::METHOD_GET, $item['@id']);
        $this->assertResponseIsSuccessful();
    }

    public function testIncorrectDeleteForeignInventoryItem(): void
    {
        $foreign = $this->createSecondSalesClientWithCredentials();
        $foreignInventoryIri = $this->createDraftInventory($foreign);
        $item = $this->addInventoryItem($foreign, $foreignInventoryIri, 'Test Product USD 1', 'B-0002', '50.000');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $item['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
