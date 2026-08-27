<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writeoffs take goods out of a named batch rather than following FIFO, because the
 * damaged or expired goods are physically identifiable.
 */
class WriteoffStockApiTest extends BaseApiTestCase
{
    public function testWriteoffTakesFromTheNamedBatchNotTheOldestOne(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');

        $writeoffIri = $this->createDraftWriteoff($client, 'Damaged pallet');
        // B-0002 is the newer batch; FIFO would have taken from B-0001.
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];

        $this->assertSame(90.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(40.0, (float) $batches[1]['remainingQty']);
    }

    /** Written-off goods are gone: a later sale cannot draw on them. */
    public function testWrittenOffGoodsAreNoLongerSellable(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $writeoffIri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '50.000');
        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // 140 were on hand, 50 are written off, so 90 remain sellable.
        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '91.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '90.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /** A writeoff never touches money: no debt and no profit entries come out of it. */
    public function testWriteoffDoesNotTouchTheMoneyLedgers(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $debtsBefore = $client->request(Request::METHOD_GET, '/api/debts')->toArray()['totalItems'];
        $profitsBefore = $client->request(Request::METHOD_GET, '/api/profits')->toArray()['totalItems'];

        $writeoffIri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '10.000');
        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertSame($debtsBefore, $client->request(Request::METHOD_GET, '/api/debts')->toArray()['totalItems']);
        $this->assertSame($profitsBefore, $client->request(Request::METHOD_GET, '/api/profits')->toArray()['totalItems']);
    }
}
