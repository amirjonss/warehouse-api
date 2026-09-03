<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\InventoryItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetInventoryItemCollection(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);
        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '140.000');

        $data = $client->request(Request::METHOD_GET, '/api/inventory_items')->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $data['totalItems']);
    }

    /** Hiding the document is not enough — its lines have their own collection endpoint. */
    public function testSuccessSellerSeesOnlyOwnInventoryItems(): void
    {
        $foreign = $this->createSecondSalesClientWithCredentials();
        $foreignInventoryIri = $this->createDraftInventory($foreign);
        $this->addInventoryItem($foreign, $foreignInventoryIri, 'Test Product USD 2', '80.000');

        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);
        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '140.000');

        $data = $client->request(Request::METHOD_GET, '/api/inventory_items')->toArray();

        $this->assertSame(1, $data['totalItems']);
        $this->assertSame('Test Product USD 1', $data['member'][0]['product']['name']);
    }

    public function testIncorrectGetInventoryItemCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/inventory_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
