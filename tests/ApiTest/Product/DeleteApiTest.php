<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Entity\Product;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    /**
     * Products are soft-deleted (DeleteAction sets deletedAt) and the read extension
     * hides anything with deletedAt set, so the row survives but stops being reachable.
     */
    public function testSuccessSoftDeleteProduct(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product No Stock']);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeletedProductDisappearsFromCollection(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product No Stock']);
        $client = $this->createAdminClientWithCredentials();

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $response = $client->request(Request::METHOD_GET, '/api/products');
        $this->assertNotContains('Test Product No Stock', array_column($response->toArray()['member'], 'name'));
    }

    public function testIncorrectDeleteProductByRole(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product No Stock']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
