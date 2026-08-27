<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Test Product USD 1" has two stock layers left by the fixtures:
 *   B-0001 - 90 units left, bought at 2.00 USD, received 2026-08-01
 *   B-0002 - 50 units left, bought at 2.50 USD, received 2026-08-05
 * Selling more than the oldest layer holds must consume it fully first and only then
 * spill into the newer, more expensive one.
 */
class SaleFifoFlowApiTest extends BaseApiTestCase
{
    public function testSellingAcrossTwoLayersConsumesTheOldestFirst(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $allocations = $this->saleItemAllocations($client, $item['@id']);
        $this->assertCount(2, $allocations);

        // Oldest layer is drained completely, the remainder comes from the newer one.
        usort($allocations, static fn (array $a, array $b): int => $a['costPrice'] <=> $b['costPrice']);
        $this->assertSame(90.0, (float) $allocations[0]['quantity']);
        $this->assertSame('2.00', $allocations[0]['costPrice']);
        $this->assertSame(30.0, (float) $allocations[1]['quantity']);
        $this->assertSame('2.50', $allocations[1]['costPrice']);
    }

    public function testProfitIsComputedPerLayerNotOnAnAveragePrice(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // 90 * (6.00 - 2.00) + 30 * (6.00 - 2.50) = 360 + 105
        $profits = $client->request(Request::METHOD_GET, '/api/profits')->toArray()['member'];
        $saleProfit = 0.0;
        foreach ($profits as $profit) {
            if (($profit['sale']['@id'] ?? null) === $saleIri) {
                $saleProfit += (float) $profit['profit'];
            }
        }

        $this->assertSame(465.0, $saleProfit);
    }

    public function testPostingDrainsBothBatchesInTheLedger(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];

        $this->assertSame(0.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(20.0, (float) $batches[1]['remainingQty']);

        // 140 on hand minus the 120 just sold.
        $product = $client->request(Request::METHOD_GET, $productIri)->toArray();
        $this->assertSame(20.0, (float) $product['remainingQty']);
    }

    public function testSellingMoreThanBothLayersHoldIsRefused(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '141.000', '6.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
