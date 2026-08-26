<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ReceiptItem;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateReceiptItemRecalculatesTotals(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);
        $item = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['price' => '39.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['total' => '390.00']);

        $receipt = $client->request(Request::METHOD_GET, $receiptIri)->toArray();
        $this->assertSame(390.0, (float) $receipt['totalUsd']);
    }

    public function testIncorrectUpdateReceiptItemWithNonPositivePrice(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);
        $item = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['price' => '0']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** A posted receipt already produced batches at these prices, so its lines are frozen. */
    public function testIncorrectUpdateReceiptItemOfPostedReceipt(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receipt = $client->request(
            Request::METHOD_GET,
            $this->findIriBy(Receipt::class, ['number' => 'RC-00001'])
        )->toArray();

        $client->request(Request::METHOD_PATCH, $receipt['items'][0]['@id'], [
            'body' => json_encode(['price' => '999.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectUpdateReceiptItemByRole(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);
        $item = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['price' => '39.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
