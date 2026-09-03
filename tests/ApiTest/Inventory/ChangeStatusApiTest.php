<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a count sheet spreads each product's difference over that product's batches.
 *
 * Fixture stock for Test Product USD 1 is 140: B-0001 = 90 (the older layer, 2.00) and
 * B-0002 = 50 (2.50).
 */
class ChangeStatusApiTest extends BaseApiTestCase
{
    /** Missing goods are the ones that were due to leave next — taken off the oldest batch. */
    public function testSuccessPostShortageComesOffTheOldestBatch(): void
    {
        $this->postCount('Test Product USD 1', '135.000');

        $admin = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(135.0, $this->productStock($admin, $productIri));

        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(85.0, (float) $batches[0]['remainingQty'], 'B-0001 is the front of the queue.');
        $this->assertSame(50.0, (float) $batches[1]['remainingQty'], 'B-0002 is untouched.');

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(-5.0, (float) $movements[0]['quantity']);
        $this->assertSame('B-0001', $movements[0]['batch']['number']);
        $this->assertSame('inventory', $movements[0]['docType']);
    }

    /** Found goods rejoin the queue where they left it: the same oldest batch, at its own cost. */
    public function testSuccessPostSurplusGoesOntoTheOldestBatch(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $batchesBefore = $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'];

        $this->postCount('Test Product USD 1', '145.000');

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(145.0, $this->productStock($admin, $productIri));

        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(95.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(50.0, (float) $batches[1]['remainingQty']);

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(5.0, (float) $movements[0]['quantity']);
        $this->assertSame('B-0001', $movements[0]['batch']['number']);

        $this->assertSame(
            $batchesBefore,
            $admin->request(Request::METHOD_GET, '/api/batches')->toArray()['totalItems'],
            'A stocktake must never invent a batch.'
        );
    }

    /** A shortage bigger than the front layer spills into the next one, exactly like a sale. */
    public function testSuccessPostShortageSpillsIntoTheNextBatch(): void
    {
        $this->postCount('Test Product USD 1', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(45.0, $this->productStock($admin, $productIri));

        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(0.0, (float) $batches[0]['remainingQty'], 'B-0001 is emptied first.');
        $this->assertSame(45.0, (float) $batches[1]['remainingQty'], 'B-0002 covers the rest.');

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(2, $movements);
        $this->assertSame(-90.0, (float) $movements[0]['quantity']);
        $this->assertSame('B-0001', $movements[0]['batch']['number']);
        $this->assertSame(-5.0, (float) $movements[1]['quantity']);
        $this->assertSame('B-0002', $movements[1]['batch']['number']);
    }

    /** With the front layer exhausted, the surplus lands on the next batch that still has stock. */
    public function testSuccessPostSurplusSkipsExhaustedBatches(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0001', '90.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        // 50 left, all of it in B-0002; the count finds 55.
        $this->postCount('Test Product USD 1', '55.000');

        $productIri = $this->productIri('Test Product USD 1');
        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(0.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(55.0, (float) $batches[1]['remainingQty']);

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame('B-0002', $movements[0]['batch']['number']);
    }

    /** "Counted and it matched" is evidence worth keeping, but it moves nothing. */
    public function testSuccessPostZeroDiffLineCreatesNoMovement(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '140.000');
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 2', '75.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'posted');

        $this->assertCount(0, $this->adjustments($admin, $this->productIri('Test Product USD 1')));
        $this->assertCount(1, $this->adjustments($admin, $this->productIri('Test Product USD 2')));

        // Both lines survive, including the one that produced nothing.
        $this->assertCount(2, $this->inventoryItems($admin, $inventoryIri));
    }

    public function testSuccessPostInventoryWhereEverythingMatches(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '140.000');

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

        $this->postCount('Test Product USD 1', '145.000');

        $this->assertSame($before, $this->moneyLedgerCounts($this->createAdminClientWithCredentials()));
    }

    /**
     * The discrepancy belongs to the moment of the count. Ten units legitimately left afterwards,
     * so posting subtracts 2 (138 counted against a snapshot of 140) and leaves the writeoff
     * standing, ending at 128 — not restoring the product to the 138 that were seen.
     */
    public function testSuccessPostUsesTheSnapshotNotLiveStock(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $line = $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1');
        $this->assertSame(140.0, (float) $line['expectedQty']);

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $sales->request(Request::METHOD_PATCH, $line['@id'], [
            'body' => json_encode(['actualQty' => '138.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
        $this->assertResponseIsSuccessful();

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $productIri = $this->productIri('Test Product USD 1');
        $this->assertSame(128.0, $this->productStock($admin, $productIri));

        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(1, $movements);
        $this->assertSame(-2.0, (float) $movements[0]['quantity']);
    }

    /**
     * A counted figure cannot be negative, but the delta lands on the live stock: counting zero
     * against a snapshot of 140 after 100 were written off would need 140 units that are not there.
     */
    public function testIncorrectPostWhenShortageExceedsLiveStock(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '0.000');

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0001', '90.000');
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(40.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));
    }

    /** A product that was never received has no batch to carry the adjustment. */
    public function testIncorrectPostForProductWithoutBatches(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product No Stock', '7.000');

        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
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
        $inventoryIri = $this->postCount('Test Product USD 1', '135.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'cancelled');

        $this->changeStatus($admin, $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(140.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));
    }

    /** The mirror follows the journal, so it lands on the very batches the posting touched. */
    public function testSuccessCancelReversesTheBatchesThePostingTouched(): void
    {
        $inventoryIri = $this->postCount('Test Product USD 1', '45.000');

        $admin = $this->createAdminClientWithCredentials();
        $this->changeStatus($admin, $inventoryIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $productIri = $this->productIri('Test Product USD 1');
        $batches = $this->batchesOf($admin, $productIri);
        $this->assertSame(90.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(50.0, (float) $batches[1]['remainingQty']);

        // Four rows stand: a cancellation appends the mirror, it never deletes history.
        $movements = $this->adjustments($admin, $productIri);
        $this->assertCount(4, $movements);
        $this->assertSame(90.0, (float) $movements[2]['quantity']);
        $this->assertSame('B-0001', $movements[2]['batch']['number']);
        $this->assertSame(5.0, (float) $movements[3]['quantity']);
        $this->assertSame('B-0002', $movements[3]['batch']['number']);
    }

    /** Reversing a surplus that has since been sold would drive the batch negative. */
    public function testIncorrectCancelSurplusAlreadySold(): void
    {
        $inventoryIri = $this->postCount('Test Product USD 1', '152.000');

        $admin = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($admin);
        $this->addWriteoffItem($admin, $writeoffIri, 'Test Product USD 1', 'B-0001', '100.000');
        $this->changeStatus($admin, $writeoffIri, 'posted');

        $this->changeStatus($admin, $inventoryIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(52.0, $this->productStock($admin, $this->productIri('Test Product USD 1')));
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, 'Test Product USD 1', '135.000');

        $this->changeStatus($sales, $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectChangeStatusAnonymously(): void
    {
        $inventoryIri = $this->createDraftInventory($this->createSalesClientWithCredentials());

        $this->changeStatus($this->createAnonymousClient(), $inventoryIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /** Counts one product and posts it, returning the document's IRI. */
    private function postCount(string $productName, string $actualQty): string
    {
        $sales = $this->createSalesClientWithCredentials();
        $inventoryIri = $this->createDraftInventory($sales);
        $this->addInventoryItem($sales, $inventoryIri, $productName, $actualQty);

        $this->changeStatus($this->createAdminClientWithCredentials(), $inventoryIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return $inventoryIri;
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
