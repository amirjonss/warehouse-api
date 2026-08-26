<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\ReceiptItem;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetReceiptItemCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/receipt_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // Four lines across the three fixture receipts.
        $this->assertSame(4, $response->toArray()['totalItems']);
    }

    /**
     * The link from a line to the batch it produced is only serialized under the
     * receipts:read group, so it shows up on the receipt rather than on /api/receipt_items.
     */
    public function testSuccessPostedReceiptLinksEachItemToItsBatch(): void
    {
        $iri = $this->findIriBy(Receipt::class, ['number' => 'RC-00002']);

        $receipt = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri)->toArray();

        $this->assertCount(2, $receipt['items']);
        foreach ($receipt['items'] as $item) {
            $this->assertNotNull($item['batch'] ?? null);
        }
    }

    public function testIncorrectGetReceiptItemCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/receipt_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
