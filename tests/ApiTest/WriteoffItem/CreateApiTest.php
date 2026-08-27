<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\WriteoffItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateWriteoffItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(5.0, (float) $item['quantity']);
    }

    public function testIncorrectCreateWriteoffItemWithNonPositiveQuantity(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '0');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** One line per batch: the same batch cannot be written off twice in one document. */
    public function testIncorrectCreateDuplicateWriteoffItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Unlike sale items, a writeoff line is not checked against the batch balance when it
     * is added: availability is asserted when the document is posted.
     */
    public function testWriteoffItemBeyondBatchQuantityIsOnlyRejectedOnPosting(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '9999.000');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** The batch has to actually belong to the product named on the line. */
    public function testIncorrectCreateWriteoffItemWithBatchOfAnotherProduct(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);

        $batches = $client->request(
            Request::METHOD_GET,
            '/api/batches?product=' . basename($this->productIri('Test Product USD 2'))
        )->toArray()['member'];

        $client->request(Request::METHOD_POST, '/api/writeoff_items', [
            'body' => json_encode([
                'writeoff' => $writeoffIri,
                'product' => $this->productIri('Test Product USD 1'),
                'batch' => $batches[0]['@id'],
                'quantity' => '1.000',
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateWriteoffItemByRole(): void
    {
        $writeoffIri = $this->createDraftWriteoff($this->createAdminClientWithCredentials());

        $this->addWriteoffItem($this->createSalesClientWithCredentials(), $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
