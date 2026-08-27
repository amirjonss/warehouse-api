<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Receipt;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetReceiptCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/receipts');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // Three receipts were posted by the stock fixtures.
        $this->assertSame(3, $response->toArray()['totalItems']);
    }

    public function testSuccessGetReceiptItem(): void
    {
        $iri = $this->findIriBy(Receipt::class, ['number' => 'RC-00001']);
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $response->toArray();
        $this->assertSame('posted', $data['status']);
        // 100 units at 2.00 USD.
        $this->assertSame('200.00', $data['totalUsd']);
    }

    public function testIncorrectGetReceiptCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/receipts');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
