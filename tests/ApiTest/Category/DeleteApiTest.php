<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Category;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteCategory(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createCategoryAndGetIri($client);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * products.category_id is NOT NULL, so a category still holding products must be
     * refused with a business error rather than a raw foreign-key violation.
     */
    public function testIncorrectDeleteCategoryThatStillHasProducts(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // The category is still there.
        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIncorrectDeleteCategoryByRole(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * The fixture categories all have products, which a delete would trip over. Takes the
     * client rather than building its own — see the note in Supplier\DeleteApiTest.
     */
    private function createCategoryAndGetIri(Client $client): string
    {
        return $this->createAndGetIri($client, '/api/categories', ['name' => 'Category To Delete']);
    }
}
