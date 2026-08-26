<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Receipt;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a receipt is the only way stock enters the system: it creates one Batch per
 * item plus an incoming StockMovement, and bumps both denormalized remainingQty caches.
 *
 * Everything is asserted through the API, never through the EntityManager.
 */
class ChangeStatusApiTest extends BaseApiTestCase
{
    private const PRODUCT = 'Test Product No Stock';

    public function testSuccessPostReceiptCreatesBatchAndStock(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri(self::PRODUCT);

        $before = $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame(0.0, (float) $before);

        $receiptIri = $this->createDraftReceiptWithItem($client, '25.000', '3.00');

        $this->changeStatus($client, $receiptIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['status' => 'posted']);
        $this->assertNotNull($client->request(Request::METHOD_GET, $receiptIri)->toArray()['postedAt']);

        // The product's stock cache grew by exactly the received quantity.
        $after = $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame(25.0, (float) $after);

        // A batch now exists for that product, carrying the purchase price.
        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri)
        )->toArray()['member'];
        $this->assertCount(1, $batches);
        $this->assertSame(25.0, (float) $batches[0]['initialQty']);
        $this->assertSame(25.0, (float) $batches[0]['remainingQty']);
        $this->assertSame('3.00', $batches[0]['purchasePrice']);

        // ...and an incoming stock movement records the same quantity.
        $movements = $client->request(
            Request::METHOD_GET,
            '/api/stock_movements?product=' . basename($productIri)
        )->toArray()['member'];
        $this->assertCount(1, $movements);
        $this->assertSame('in', $movements[0]['type']);
        $this->assertSame(25.0, (float) $movements[0]['quantity']);
    }

    public function testIncorrectPostReceiptWithoutItems(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);

        $this->changeStatus($client, $receiptIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Posting twice must not double the stock. */
    public function testPostingAnAlreadyPostedReceiptIsANoop(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceiptWithItem($client, '25.000', '3.00');

        $this->changeStatus($client, $receiptIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->changeStatus($client, $receiptIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $after = $client->request(Request::METHOD_GET, $this->productIri(self::PRODUCT))->toArray()['remainingQty'];
        $this->assertSame(25.0, (float) $after);
    }

    public function testIncorrectMovePostedReceiptBackToDraft(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $this->changeStatus($client, $this->findIriBy(Receipt::class, ['number' => 'RC-00001']), 'draft');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** A batch already consumed by a sale pins the receipt: cancelling it would break the ledger. */
    public function testIncorrectCancelReceiptWhoseBatchIsAlreadyUsed(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $this->changeStatus($client, $this->findIriBy(Receipt::class, ['number' => 'RC-00001']), 'cancelled');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSuccessCancelReceiptReturnsStock(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceiptWithItem($client, '25.000', '3.00');

        $this->changeStatus($client, $receiptIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->changeStatus($client, $receiptIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Cancelling writes a compensating movement, so the stock cache is back to zero.
        $after = $client->request(Request::METHOD_GET, $this->productIri(self::PRODUCT))->toArray()['remainingQty'];
        $this->assertSame(0.0, (float) $after);
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $iri = $this->findIriBy(Receipt::class, ['number' => 'RC-00001']);

        $this->changeStatus($this->createSalesClientWithCredentials(), $iri, 'cancelled');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function createDraftReceiptWithItem(Client $client, string $quantity, string $price): string
    {
        $receiptIri = $this->createDraftReceipt($client);
        $this->addReceiptItem($client, $receiptIri, self::PRODUCT, $quantity, $price);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $receiptIri;
    }
}
