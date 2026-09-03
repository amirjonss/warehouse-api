<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\InventoryItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateInventoryItemAsSales(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $data = $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '135.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // The snapshot is the whole product, 90 in B-0001 plus 50 in B-0002.
        $this->assertSame(140.0, (float) $data['expectedQty']);
        $this->assertSame(135.0, (float) $data['actualQty']);
        $this->assertSame(-5.0, (float) $data['diffQty']);
    }

    public function testSuccessCreateInventoryItemWithoutActualQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $data = $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertNull($data['actualQty']);
        $this->assertNull($data['diffQty']);
    }

    /** Zero is a legitimate count — the shelf was checked and it was empty. */
    public function testSuccessCreateInventoryItemWithZeroActualQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $data = $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '0.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(0.0, (float) $data['actualQty']);
        $this->assertSame(-140.0, (float) $data['diffQty']);
    }

    /** Otherwise the counter could erase their own discrepancy on the way in. */
    public function testSuccessExpectedQtyIsSnapshottedFromTheLedgerNotTheClient(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $response = $client->request(Request::METHOD_POST, '/api/inventory_items', [
            'body' => json_encode([
                'inventory' => $inventoryIri,
                'product' => $this->productIri('Test Product USD 1'),
                'actualQty' => '140.000',
                'expectedQty' => '1.000',
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(140.0, (float) $response->toArray()['expectedQty']);
    }

    public function testIncorrectCreateInventoryItemWithNegativeActualQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '-1.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateDuplicateInventoryItem(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '140.000');
        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', '139.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateInventoryItemOnPostedInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '140.000');
        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $again = $this->createSalesClientWithCredentials();
        $this->addInventoryItem($again, $inventoryIri, 'Test Product USD 2', '80.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * The seller cannot even name a foreign sheet: the query extension hides it, so the IRI
     * fails to resolve during denormalisation and the request never reaches the service.
     */
    public function testIncorrectCreateInventoryItemOnForeignInventory(): void
    {
        $foreignIri = $this->createDraftInventory($this->createSecondSalesClientWithCredentials());

        $client = $this->createSalesClientWithCredentials();
        $this->addInventoryItem($client, $foreignIri, 'Test Product USD 1', '140.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIncorrectCreateInventoryItemAnonymously(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/inventory_items', [
            'body' => json_encode(['inventory' => $inventoryIri]),
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
