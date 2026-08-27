<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ReceiptItem;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateReceiptItemAndRecalculateTotals(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);

        $item = $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '100.000', '38.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // total is derived server side: 100 * 38.00
        $this->assertSame('3800.00', $item['total']);

        $receipt = $client->request(Request::METHOD_GET, $receiptIri)->toArray();
        $this->assertSame(3800.0, (float) $receipt['totalUsd']);
    }

    public function testIncorrectCreateReceiptItemWithNonPositiveQuantity(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);

        $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '0', '38.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'quantity']]]);
    }

    public function testIncorrectCreateReceiptItemWithNonPositivePrice(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);

        $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '-1.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'price']]]);
    }

    /** One line per product: the same product cannot be added to a receipt twice. */
    public function testIncorrectCreateDuplicateReceiptItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $receiptIri = $this->createDraftReceipt($client);

        $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->addReceiptItem($client, $receiptIri, 'Test Product USD 1', '10.000', '38.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Items may only be added while the receipt is still a draft. */
    public function testIncorrectCreateReceiptItemOnPostedReceipt(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $postedIri = $this->findIriBy(Receipt::class, ['number' => 'RC-00001']);

        $this->addReceiptItem($client, $postedIri, 'Test Product No Stock', '10.000', '38.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateReceiptItemByRole(): void
    {
        $receiptIri = $this->createDraftReceipt($this->createAdminClientWithCredentials());

        $this->addReceiptItem($this->createSalesClientWithCredentials(), $receiptIri, 'Test Product USD 1', '10.000', '38.00');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
