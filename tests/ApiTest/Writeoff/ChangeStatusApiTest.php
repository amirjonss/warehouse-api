<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Writeoff;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Posting a writeoff removes goods from a named batch and logs a writeoff movement. */
class ChangeStatusApiTest extends BaseApiTestCase
{
    public function testSuccessPostWriteoffRemovesStock(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');

        $stockBefore = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];

        $writeoffIri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Both the product cache and the named batch went down by the written-off quantity.
        $stockAfter = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame($stockBefore - 5.0, $stockAfter);

        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($productIri) . '&order[receivedAt]=asc'
        )->toArray()['member'];
        // B-0001 is untouched, B-0002 lost the 5 units.
        $this->assertSame(90.0, (float) $batches[0]['remainingQty']);
        $this->assertSame(45.0, (float) $batches[1]['remainingQty']);

        $movements = $client->request(
            Request::METHOD_GET,
            '/api/stock_movements?product=' . basename($productIri) . '&type=writeoff'
        )->toArray()['member'];
        $this->assertCount(1, $movements);
        $this->assertSame(-5.0, (float) $movements[0]['quantity']);
    }

    public function testIncorrectPostWriteoffWithoutItems(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $this->changeStatus($client, $writeoffIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Cancelling is final: re-posting would write the goods off a second time. */
    public function testIncorrectPostCancelledWriteoff(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $productIri = $this->productIri('Test Product USD 1');
        $stockBefore = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];

        $writeoffIri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');
        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->changeStatus($client, $writeoffIri, 'cancelled');

        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $stockAfter = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame($stockBefore, $stockAfter);
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $writeoffIri = $this->createDraftWriteoff($this->createAdminClientWithCredentials());

        $this->changeStatus($this->createSalesClientWithCredentials(), $writeoffIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
