<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Entity\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DebtAgingApiTest extends BaseApiTestCase
{
    public function testSuccessGetDebtAging(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/clients/debt-aging');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $items = $response->toArray()['items'];

        // Only the client holding the posted fixture sale shows up, keyed by id.
        $this->assertCount(1, $items);
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 2']);
        $this->assertSame((int) basename($iri), $items[0]['clientId']);
        $this->assertNotEmpty($items[0]['oldestDebtDate']);
    }

    public function testIncorrectGetDebtAgingAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/clients/debt-aging');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
