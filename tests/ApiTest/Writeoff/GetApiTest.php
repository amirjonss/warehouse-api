<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Writeoff;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetWriteoffCollection(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->createDraftWriteoff($client);

        $response = $client->request(Request::METHOD_GET, '/api/writeoffs');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessGetWriteoffItem(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createDraftWriteoff($client, 'Damaged in transit');

        $client->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'reason' => 'Damaged in transit']);
    }

    public function testIncorrectGetWriteoffCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/writeoffs');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
