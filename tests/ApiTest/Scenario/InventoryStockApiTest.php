<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a posted stocktake means for the rest of the system: the goods it finds are real goods,
 * the goods it loses are gone, and no money moves either way.
 */
class InventoryStockApiTest extends BaseApiTestCase
{
    /** Found goods rejoin the batch they were missing from, at that batch's own cost. */
    public function testSurplusIsSellableFromTheSameBatch(): void
    {
        $this->postCount('B-0002', '60.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->assertSame(150.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));

        $this->assertTrue($this->canSell($admin, '150.000'));
        $this->assertFalse($this->canSell($admin, '151.000'));
    }

    public function testShortageRemovesGoodsFromCirculation(): void
    {
        $this->postCount('B-0002', '40.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->assertSame(130.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));

        $this->assertFalse($this->canSell($admin, '140.000'));
        $this->assertTrue($this->canSell($admin, '130.000'));
    }

    public function testInventoryNeverTouchesTheMoneyLedgers(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $before = $this->ledgerCounts($admin);

        $this->postCount('B-0002', '60.000');
        $this->postCount('B-0001', '80.000');

        $this->assertSame($before, $this->ledgerCounts($this->createAdminClientWithCredentials()));
    }

    public function testInventoryDoesNotCreateNewBatches(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $before = $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $this->postCount('B-0002', '60.000');

        $after = $this->createAdminClientWithCredentials()
            ->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $this->assertSame($before, $after);
    }

    /**
     * The denormalised product total is only ever the sum of its batches — the invariant that
     * lets the posting service check non-negativity per batch and stop there.
     */
    public function testProductCacheMatchesTheSumOfItsBatchesAfterInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0001', '85.000');
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '57.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $productIri = $this->productIri('Test Product USD 1');
        $batches = $admin->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri)
        )->toArray()['member'];

        $sum = 0.0;
        foreach ($batches as $batch) {
            $sum += (float) $batch['remainingQty'];
        }

        $this->assertSame(142.0, $sum);
        $this->assertSame($sum, $this->productStock($admin, $productIri));
    }

    private function postCount(string $batchNumber, string $actualQty): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', $batchNumber, $actualQty);

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
