<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\WriteoffItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteWriteoffItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $client->request(Request::METHOD_DELETE, $item['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $response = $client->request(Request::METHOD_GET, '/api/writeoff_items');
        $this->assertSame(0, $response->toArray()['totalItems']);
    }

    /**
     * Posting already moved the goods out of the batch and logged the movement, so removing
     * the line afterwards would leave that movement without a source and desynchronise
     * batch.remainingQty. Undoing a posted writeoff means cancelling the document.
     */
    public function testIncorrectDeleteWriteoffItemOfPostedWriteoff(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(Request::METHOD_DELETE, $item['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // The line is still there and the batch still reflects the writeoff.
        $client->request(Request::METHOD_GET, $item['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIncorrectDeleteWriteoffItemByRole(): void
    {
        $adminClient = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($adminClient);
        $item = $this->addWriteoffItem($adminClient, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $item['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
