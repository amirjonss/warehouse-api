<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ReceiptItem;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteReceiptItemRecalculatesTotals(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);
        $first = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');
        $this->addReceiptItem($client, $receiptIri, 'Test Product USD 2', '5.000', '10.00');

        $client->request(Request::METHOD_DELETE, $first['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Only the second line is left: 5 * 10.00
        $receipt = $client->request(Request::METHOD_GET, $receiptIri)->toArray();
        $this->assertSame(50.0, (float) $receipt['totalUsd']);
    }

    /**
     * Posting turned this line into a batch plus an incoming movement, so it may not be
     * removed afterwards — the receipt has to be cancelled instead.
     */
    public function testIncorrectDeleteReceiptItemOfPostedReceipt(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receipt = $client->request(
            Request::METHOD_GET,
            $this->findIriBy(Receipt::class, ['number' => 'RC-00001'])
        )->toArray();

        $client->request(Request::METHOD_DELETE, $receipt['items'][0]['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteReceiptItemByRole(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);
        $item = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $item['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
