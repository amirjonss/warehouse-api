<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a count sheet books the discrepancy against the batches that were counted.
 *
 * Fixture stock for Test Product USD 1 is 140: B-0001 = 90, B-0002 = 50.
 */
class ChangeStatusApiTest extends BaseApiTestCase
{
    public function testSuccessPostShortageReducesStock(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(135.0, $this->productStock($admin, $productIri));

        // Only the counted batch moved; its neighbour is untouched.
        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(90.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(45.0, (float) $batches[1]['remainingQty']);

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(-5.0, (float) $movements[0]['quantity']);
        $this->assertSame('inventory', $movements[0]['docType']);
    }

    /** A surplus goes back where it was missing from — never into a brand new batch. */
    public function testSuccessPostSurplusGoesBackOnTheSameBatch(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $batchesBefore = $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '53.000');

        $this->changeStatus($admin, $inventoryIri, 'posted');

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(143.0, $this->productStock($admin, $productIri));
        $this->assertSame(53.0, (float) $this->batchesOf($admin, $productIri)[1]['remainingQty']);

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(3.0, (float) $movements[0]['quantity']);

        $this->assertSame(
            $batchesBefore,
            $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'],
            'A stocktake must never invent a batch.'
        );
    }

    /** "Counted and it matched" is evidence worth keeping, but it moves nothing. */
    public function testSuccessPostZeroDiffLineCreatesNoMovement(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0001', '90.000');
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertCount(1, $this->adjustments($admin, $productIri));

        // Both lines survive, including the one that produced nothing.
        $this->assertCount(2, $this->inventoryItems($admin, $inventoryIri));
    }

    public function testSuccessPostInventoryWhereEverythingMatches(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '50.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $admin->request(Request::METHOD_GET, $inventoryIri)->toArray();
        $this->assertSame('posted', $data['status']);
        $this->assertNotNull($data['postedAt']);
        $this->assertCount(0, $this->adjustments($admin, $this->productIri('Test Product USD 1')));
    }

    public function testSuccessPostDoesNotTouchTheMoneyLedgers(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $before = $this->moneyLedgerCounts($admin);

        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '53.000');
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $this->assertSame($before, $this->moneyLedgerCounts($admin));
    }

    /**
     * The discrepancy belongs to the moment of the count. Ten units legitimately left B-0002
     * afterwards, so posting must subtract 2 (48 counted against a snapshot of 50) and leave
     * the writeoff standing, ending at 38 — not restore the batch to the 48 that were seen.
     */
    public function testSuccessPostUsesTheSnapshotNotLiveStock(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $line = $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002');
        $this->assertSame(50.0, (float) $line['expectedQty']);

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $sales->request(Request::METHOD_PATCH, $line['@id'], [
            'body' => json_encode(['actualQty' => '48.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
        $this->assertResponseIsSuccessful();

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(38.0, (float) $this->batchesOf($admin, $productIri)[1]['remainingQty']);

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(-2.0, (float) $movements[0]['quantity']);
    }

    /**
     * A counted figure cannot be negative, but the delta lands on the live remainder: counting
     * zero against a snapshot of 50 after 45 were written off would drive the batch to -45.
     */
    public function testIncorrectPostWhenShortageWouldMakeBatchNegative(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '0.000');

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '45.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(5.0, (float) $this->batchesOf($admin, $productIri)[1]['remainingQty']);
    }

    /** A half-finished sheet must not be read as "everything else is missing". */
    public function testIncorrectPostWithUncountedLines(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->fillInventory($sales, $inventoryIri);

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(140.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));
    }

    public function testIncorrectPostInventoryWithoutItems(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectPostCancelledInventory(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->changeStatus($admin, $inventoryIri, 'cancelled');

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(140.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));
    }

    public function testSuccessCancelReversesAdjustments(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->changeStatus($admin, $inventoryIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(50.0, (float) $this->batchesOf($admin, $productIri)[1]['remainingQty']);

        // Both rows stand: a cancellation appends the mirror, it never deletes history.
        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(2, $movements);
        $this->assertSame(-5.0, (float) $movements[0]['quantity']);
        $this->assertSame(5.0, (float) $movements[1]['quantity']);
    }

    /** Reversing a surplus that has since been sold would drive the batch negative. */
    public function testIncorrectCancelSurplusAlreadySold(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '62.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '60.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $this->changeStatus($admin, $inventoryIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(2.0, (float) $this->batchesOf($admin, $productIri)[1]['remainingQty']);
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', 'B-0002', '45.000');

        $this->changeStatus($sales, $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectChangeStatusAnonymously(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->changeStatus($this->createAnonymousClient(), $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function productStock(object $client, string $productIri): float
    {
        return (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
    }

    /** @return array<int, array<string, mixed>> */
    private function batchesOf(object $client, string $productIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];
    }

    /** @return array<int, array<string, mixed>> */
    private function adjustments(object $client, string $productIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/stock_movements?product=' . basename($productIri) . '&type=adjust&order[id]=asc'
        )->toArray()['member'];
    }

    /** @return array<string, int> */
    private function moneyLedgerCounts(object $client): array
    {
        $counts = [];
        foreach (['/api/debts', '/api/profits', '/api/supplier_debts', '/api/receipts'] as $uri) {
            $counts[$uri] = $client->request(Request::METHOD_GET, $uri)->toArray()['totalItems'];
        }

        return $counts;
    }
}
