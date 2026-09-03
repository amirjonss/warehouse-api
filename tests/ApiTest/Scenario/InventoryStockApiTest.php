<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a posted stocktake means for the rest of the system: the goods it finds are real goods,
 * the goods it loses are gone, and no money moves either way.
 *
 * Test Product USD 1 starts at 140 — B-0001 = 90 at 2.00, B-0002 = 50 at 2.50.
 */
class InventoryStockApiTest extends BaseApiTestCase
{
    /** Found goods rejoin the front of the queue, so they are the next ones sold. */
    public function testSurplusIsSellableAndLeavesFirst(): void
    {
        $this->postCount('Test Product USD 1', '150.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->assertSame(150.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));

        $this->assertTrue($this->canSell($admin, '150.000'));
        $this->assertFalse($this->canSell($admin, '151.000'));
    }

    public function testShortageRemovesGoodsFromCirculation(): void
    {
        $this->postCount('Test Product USD 1', '130.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->assertSame(130.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));

        $this->assertFalse($this->canSell($admin, '140.000'));
        $this->assertTrue($this->canSell($admin, '130.000'));
    }

    /** A surplus is costed at the batch it joins, so the margin on it is the old batch's margin. */
    public function testSurplusIsCostedAtTheBatchItJoins(): void
    {
        $this->postCount('Test Product USD 1', '150.000');

        $admin = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($admin);
        $saleItem = $this->addSaleItem($admin, $saleIri, 'Test Product USD 1', '95.000', '5.00');
        $this->changeStatus($admin, $saleIri, 'posted');

        // 95 units all come out of B-0001, which now holds 95 after the surplus joined it.
        $allocations = $this->saleItemAllocations($admin, $saleItem['@id']);
        $this->assertCount(1, $allocations);
        $this->assertSame('2.00', $allocations[0]['costPrice']);
    }

    public function testInventoryNeverTouchesTheMoneyLedgers(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $before = $this->ledgerCounts($admin);

        $this->postCount('Test Product USD 1', '150.000');
        $this->postCount('Test Product USD 2', '75.000');

        $this->assertSame($before, $this->ledgerCounts($this->createAdminClientWithCredentials()));
    }

    public function testInventoryDoesNotCreateNewBatches(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $before = $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $this->postCount('Test Product USD 1', '150.000');

        $after = $this->createAdminClientWithCredentials()
            ->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $this->assertSame($before, $after);
    }

    /**
     * The denormalised product total is only ever the sum of its batches — the invariant the
     * whole spreading exercise has to preserve.
     */
    public function testProductCacheMatchesTheSumOfItsBatchesAfterInventory(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $this->postCount('Test Product USD 1', '45.000');

        $productIri = $this->productIri('Test Product USD 1');
        $batches = $admin->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri)
        )->toArray()['member'];

        $sum = 0.0;
        foreach ($batches as $batch) {
            $sum += (float) $batch['remainingQty'];
        }

        $this->assertSame(45.0, $sum);
        $this->assertSame($sum, $this->productStock($admin, $productIri));
    }

    private function postCount(string $productName, string $actualQty): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, $productName, $actualQty);

        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /** Posts a sale of the given quantity and reports whether the stock covered it. */
    private function canSell(object $client, string $quantity): bool
    {
        $saleIri = $this->createDraftSale($client);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', $quantity, '5.00');
        $this->changeStatus($client, $saleIri, 'posted');

        $posted = $client->request(Request::METHOD_GET, $saleIri)->toArray()['status'] === 'posted';

        if ($posted) {
            $this->changeStatus($client, $saleIri, 'cancelled');
        }

        return $posted;
    }

    private function productStock(object $client, string $productIri): float
    {
        return (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
    }

    /** @return array<string, int> */
    private function ledgerCounts(object $client): array
    {
        $counts = [];
        foreach (['/api/debts', '/api/profits', '/api/supplier_debts'] as $uri) {
            $counts[$uri] = $client->request(Request::METHOD_GET, $uri)->toArray()['totalItems'];
        }

        return $counts;
    }
}
