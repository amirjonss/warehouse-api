<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteDraftInventory(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $client->request(Request::METHOD_DELETE, $inventoryIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $inventoryIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSuccessDeleteDraftCascadesItems(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);
        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 2', '80.000');
        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '140.000');

        $client->request(Request::METHOD_DELETE, $inventoryIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $data = $client->request(Request::METHOD_GET, '/api/inventory_items')->toArray();
        $this->assertSame(0, $data['totalItems']);
    }

    /** A posted sheet is evidence — the way to undo it is a cancellation, not a delete. */
    public function testIncorrectDeletePostedInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '135.000');
        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $inventoryIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteForeignInventory(): void
    {
        $foreignIri = $this->createDraftInventory($this->createSecondSalesClientWithCredentials());

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $foreignIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectDeleteInventoryAnonymously(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $inventoryIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
