<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Entity\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetClientCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/clients');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertContains('Test Client 1', array_column($response->toArray()['member'], 'name'));
    }

    public function testSuccessGetClientItem(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 1']);
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'name' => 'Test Client 1']);
    }

    /** "Test Client 2" carries the debt left by the posted fixture sale. */
    public function testSuccessFilterClientsWithDebt(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/clients?hasDebt=true'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $names = array_column($response->toArray()['member'], 'name');
        $this->assertContains('Test Client 2', $names);
        $this->assertNotContains('Test Client 1', $names);
    }

    public function testSuccessFilterClientsByName(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/clients?name=Client 3'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(['Test Client 3'], array_column($response->toArray()['member'], 'name'));
    }

    public function testIncorrectGetClientCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/clients');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
