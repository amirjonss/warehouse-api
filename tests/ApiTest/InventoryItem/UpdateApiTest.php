<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\InventoryItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateActualQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $itemIri = $this->line($client, '140.000');

        $data = $this->patch($client, $itemIri, '135.000')->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(135.0, (float) $data['actualQty']);
        $this->assertSame(-5.0, (float) $data['diffQty']);
    }

    public function testSuccessUpdateActualQtyToZero(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $itemIri = $this->line($client, '140.000');

        $data = $this->patch($client, $itemIri, '0.000')->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(0.0, (float) $data['actualQty']);
    }

    /** The snapshot is the whole point: editing the count must not re-read the ledger. */
    public function testSuccessUpdateDoesNotMoveTheExpectedSnapshot(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $itemIri = $this->line($client);

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $data = $this->patch($this->createSalesClientWithCredentials(), $itemIri, '130.000')->toArray();

        $this->assertSame(140.0, (float) $data['expectedQty']);
    }

    public function testIncorrectUpdateExpectedQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $itemIri = $this->line($client, '140.000');

        $response = $client->request(Request::METHOD_PATCH, $itemIri, [
            'body' => json_encode(['expectedQty' => '1.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(140.0, (float) $response->toArray()['expectedQty']);
    }

    public function testIncorrectUpdateWithNegativeActualQty(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $itemIri = $this->line($client, '140.000');

        $this->patch($client, $itemIri, '-1.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectUpdateItemOfPostedInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $item = $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '140.000');
        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $this->patch($this->createSalesClientWithCredentials(), $item['@id'], '135.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectUpdateForeignInventoryItem(): void
    {
        $foreignItemIri = $this->line($this->createSecondSalesClientWithCredentials(), '140.000');

        $this->patch($this->createSalesClientWithCredentials(), $foreignItemIri, '135.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function line(object $client, ?string $actualQty = null): string
    {
        $inventoryIri = $this->createDraftInventory($client);

        return $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', $actualQty)['@id'];
    }

    private function patch(object $client, string $itemIri, string $actualQty): object
    {
        return $client->request(Request::METHOD_PATCH, $itemIri, [
            'body' => json_encode(['actualQty' => $actualQty]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
    }
}
