<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The strongest invariant in the system: posting a sale and then cancelling it must
 * leave every ledger exactly as it was. Nothing is ever updated in place — each
 * reversal is a compensating row — so the sums, not the row counts, go back to zero.
 */
class SaleCancelReversalApiTest extends BaseApiTestCase
{
    public function testPostThenCancelRestoresEveryBalance(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');
        $customerIri = $this->clientIri('Test Client 1');

        $before = $this->snapshot($client, $productIri, $customerIri);

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Everything really did move while the sale was posted.
        $during = $this->snapshot($client, $productIri, $customerIri);
        $this->assertNotSame($before['stock'], $during['stock']);
        $this->assertNotSame($before['debtUsd'], $during['debtUsd']);

        $this->changeStatus($client, $saleIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $after = $this->snapshot($client, $productIri, $customerIri);
        $this->assertSame($before['stock'], $after['stock']);
        $this->assertSame($before['debtUsd'], $after['debtUsd']);
        $this->assertSame($before['batchQuantities'], $after['batchQuantities']);
    }

    public function testCancelledSaleNetsOutTheProfitLedger(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '120.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->changeStatus($client, $saleIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $profits = $client->request(Request::METHOD_GET, '/api/profits')->toArray()['member'];

        $realized = 0.0;
        $reversed = 0.0;
        foreach ($profits as $profit) {
            if (($profit['sale']['@id'] ?? null) !== $saleIri) {
                continue;
            }
            if ($profit['type'] === 'reversed') {
                $reversed += (float) $profit['profit'];
            } else {
                $realized += (float) $profit['profit'];
            }
        }

        $this->assertGreaterThan(0.0, $realized);
        $this->assertSame(0.0, $realized + $reversed);
    }

    /** Cancelling releases the stock, so the very same goods can be sold again. */
    public function testStockFreedByACancelCanBeSoldAgain(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $firstSale = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $firstSale, 'Test Product USD 1', '140.000', '6.00');
        $this->changeStatus($client, $firstSale, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Nothing is left on hand, so a second sale cannot be lined up.
        $secondSale = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $secondSale, 'Test Product USD 1', '10.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->changeStatus($client, $firstSale, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->addSaleItem($client, $secondSale, 'Test Product USD 1', '10.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * @return array{stock: float, debtUsd: float, batchQuantities: array<int, float>}
     */
    private function snapshot(object $client, string $productIri, string $customerIri): array
    {
        $product = $client->request(Request::METHOD_GET, $productIri)->toArray();
        $customer = $client->request(Request::METHOD_GET, $customerIri)->toArray();
        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];

        return [
            'stock' => (float) $product['remainingQty'],
            'debtUsd' => (float) $customer['debtUsd'],
            'batchQuantities' => array_map(static fn (array $b): float => (float) $b['remainingQty'], $batches),
        ];
    }
}
