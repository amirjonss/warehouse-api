<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Entity\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteClientByAdmin(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 1']);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * sales.customer_id, payments.client_id and debts.client_id all restrict deletion, so a
     * client with any history must be refused with a business error, not a raw FK violation.
     */
    public function testIncorrectDeleteClientThatHasSales(): void
    {
        $iri = $this->clientIri('Test Client 2');

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // The client is still there, with their debt untouched.
        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'debtUsd' => '50.00']);
    }

    /** A client only becomes undeletable once they actually have history. */
    public function testIncorrectDeleteClientThatOnlyHasAPayment(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->clientIri('Test Client 1');

        $this->createDraftPayment($client, 'Test Client 1', '10.00');

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Sales may create and edit clients, but deleting is reserved for admins. */
    public function testIncorrectDeleteClientByRole(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 1']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
