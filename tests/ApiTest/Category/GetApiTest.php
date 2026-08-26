<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Category;

use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetCategoryCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $names = array_column($response->toArray()['member'], 'name');
        $this->assertContains('Test Category 1', $names);
        $this->assertContains('Test Category 2', $names);
    }

    public function testSuccessGetCategoryItem(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);
        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'name' => 'Test Category 1']);
    }

    /** Reading is allowed for ROLE_SALES, only writing is admin-only. */
    public function testSuccessGetCategoryCollectionBySalesRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIncorrectGetCategoryCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
