<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Supplier;

use App\Entity\Supplier;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetSupplierCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/suppliers');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertContains('Test Supplier 1', array_column($response->toArray()['member'], 'name'));
    }

    public function testSuccessGetSupplierItem(): void
    {
        $iri = $this->findIriBy(Supplier::class, ['name' => 'Test Supplier 1']);
        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'name' => 'Test Supplier 1']);
    }

    /** Suppliers are admin-only even for reading, unlike categories and products. */
    public function testIncorrectGetSupplierCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/suppliers');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectGetSupplierCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/suppliers');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
