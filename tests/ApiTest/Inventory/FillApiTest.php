<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filling a sheet from the ledger. Fixture stock is four live batches: Test Product USD 1 has
 * B-0001 (90 after the fixture sale) and B-0002 (50), Test Product USD 2 has B-0001 (80) and
 * Test Product UZS 1 has B-0001 (200).
 */
class FillApiTest extends BaseApiTestCase
{
    public function testSuccessFillWholeWarehouse(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $data = $this->fillInventory($client, $inventoryIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(4, $data['items']);

        foreach ($data['items'] as $item) {
            $this->assertNull($item['actualQty'], 'A freshly filled line must not pretend it was counted.');
            $this->assertNull($item['diffQty']);
        }

        $usdOne = $this->lineFor($data['items'], 'Test Product USD 1', 'B-0001');
        $this->assertSame(90.0, (float) $usdOne['expectedQty']);
    }

    public function testSuccessFillByCategoryOnlyTakesThatCategory(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $data = $this->fillInventory($client, $inventoryIri, 'Test Category 1');

        $this->assertCount(3, $data['items']);
        foreach ($data['items'] as $item) {
            $this->assertNotSame('Test Product UZS 1', $item['product']['name']);
        }
    }

    /** A category on the header is the default scope, so fill needs no argument of its own. */
    public function testSuccessFillFallsBackToTheHeaderCategory(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client, null, 'Test Category 2');

        $data = $this->fillInventory($client, $inventoryIri);

        $this->assertCount(1, $data['items']);
        $this->assertSame('Test Product UZS 1', $data['items'][0]['product']['name']);
    }

    public function testSuccessFillSkipsBatchesWithoutStock(): void
    {
        $this->emptyBatchB0002();

        $client = $this->createSalesClientWithCredentials();
        $data = $this->fillInventory($client, $this->createDraftInventory($client));

        $this->assertCount(3, $data['items']);
        $this->assertNull($this->lineFor($data['items'], 'Test Product USD 1', 'B-0002'));
    }

    /** Goods found that the ledger says are gone can only be recorded on an exhausted batch. */
    public function testSuccessFillIncludesZeroStockWhenAsked(): void
    {
        $this->emptyBatchB0002();

        $client = $this->createSalesClientWithCredentials();
        $data = $this->fillInventory($client, $this->createDraftInventory($client), null, true);

        $emptied = $this->lineFor($data['items'], 'Test Product USD 1', 'B-0002');
        $this->assertNotNull($emptied);
        $this->assertSame(0.0, (float) $emptied['expectedQty']);
    }

    public function testSuccessFillIsIdempotent(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $this->fillInventory($client, $inventoryIri);
        $data = $this->fillInventory($client, $inventoryIri);

        $this->assertCount(4, $data['items']);
    }

    /** Counting category A, then filling category B, must not disturb what A already recorded. */
    public function testSuccessFillPreservesAlreadyEnteredCounts(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $filled = $this->fillInventory($client, $inventoryIri);
        $line = $this->lineFor($filled['items'], 'Test Product USD 1', 'B-0001');

        $client->request(Request::METHOD_PATCH, $line['@id'], [
            'body' => json_encode(['actualQty' => '88.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
        $this->assertResponseIsSuccessful();

        $refilled = $this->fillInventory($client, $inventoryIri);
        $sameLine = $this->lineFor($refilled['items'], 'Test Product USD 1', 'B-0001');

        $this->assertSame(88.0, (float) $sameLine['actualQty']);
        $this->assertSame(90.0, (float) $sameLine['expectedQty']);
    }

    public function testSuccessFillAfterManualLineDoesNotDuplicateThatBatch(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($client);

        $this->addInventoryItem($client, $inventoryIri, 'Test Product USD 1', 'B-0002', '50.000');
        $data = $this->fillInventory($client, $inventoryIri);

        $this->assertCount(4, $data['items']);
    }

    public function testIncorrectFillPostedInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '50.000');
        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $this->fillInventory($this->createSalesClientWithCredentials(), $inventoryIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectFillForeignInventory(): void
    {
        $foreignIri = $this->createDraftInventory($this->createSecondSalesClientWithCredentials());

        $this->fillInventory($this->createSalesClientWithCredentials(), $foreignIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectFillAnonymously(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->createAnonymousClient()->request(Request::METHOD_POST, $inventoryIri . '/fill', [
            'body' => json_encode([]),
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /** Empties B-0002 through a posted writeoff so the batch is live but exhausted. */
    private function emptyBatchB0002(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '50.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function lineFor(array $items, string $productName, string $batchNumber): ?array
    {
        foreach ($items as $item) {
            if ($item['product']['name'] === $productName && $item['batch']['number'] === $batchNumber) {
                return $item;
            }
        }

        return null;
    }
}
