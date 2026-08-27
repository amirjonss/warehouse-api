<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\WriteoffItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetWriteoffItemCollection(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $response = $client->request(Request::METHOD_GET, '/api/writeoff_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testIncorrectGetWriteoffItemCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/writeoff_items');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
