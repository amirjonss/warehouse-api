<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Writeoff;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteDraftWriteoff(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createDraftWriteoff($client);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectDeletePostedWriteoff(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createDraftWriteoff($client);
        $this->addWriteoffItem($client, $iri, 'Test Product USD 1', 'B-0002', '2.000');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->changeStatus($client, $iri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteWriteoffByRole(): void
    {
        $iri = $this->createDraftWriteoff($this->createAdminClientWithCredentials());

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
