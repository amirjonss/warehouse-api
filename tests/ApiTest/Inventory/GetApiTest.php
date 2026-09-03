<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** A count sheet is somebody's accountability: a seller only ever sees their own. */
class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetInventoryCollection(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createDraftInventory($client, 'Плановый пересчёт');

        $response = $client->request(Request::METHOD_GET, '/api/inventories');

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessGetInventoryItem(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client, 'Плановый пересчёт');

        $response = $client->request(Request::METHOD_GET, $inventoryIri);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame($inventoryIri, $data['@id']);
        $this->assertSame('Плановый пересчёт', $data['note']);
    }

    public function testSuccessSellerSeesOnlyOwnInventories(): void
    {
        $this->createDraftInventory($this->createSecondSalesClientWithCredentials(), 'Чужой пересчёт');

        $client = $this->createSalesClientWithCredentials();
        $ownIri = $this->createDraftInventory($client, 'Свой пересчёт');

        $data = $client->request(Request::METHOD_GET, '/api/inventories')->toArray();

        $this->assertSame(1, $data['totalItems']);
        $this->assertSame($ownIri, $data['member'][0]['@id']);
    }

    public function testSuccessAdminSeesEveryInventory(): void
    {
        $this->createDraftInventory($this->createSecondSalesClientWithCredentials(), 'Чужой пересчёт');
        $this->createDraftInventory($this->createSalesClientWithCredentials(), 'Свой пересчёт');

        $data = $this->createAdminClientWithCredentials()
            ->request(Request::METHOD_GET, '/api/inventories')
            ->toArray();

        $this->assertSame(2, $data['totalItems']);
    }

    /** Hidden by the query extension rather than by security, so the answer is 404, not 403. */
    public function testIncorrectGetForeignInventoryItem(): void
    {
        $foreignIri = $this->createDraftInventory($this->createSecondSalesClientWithCredentials());

        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $foreignIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectGetInventoryCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/inventories');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
