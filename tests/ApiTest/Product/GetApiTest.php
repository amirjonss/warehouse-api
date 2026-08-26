<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Entity\Category;
use App\Entity\Product;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetProductCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/products');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertContains('Test Product USD 1', array_column($response->toArray()['member'], 'name'));
    }

    /** remainingQty is the denormalized cache the receipt/sale postings keep in sync. */
    public function testSuccessGetProductItemShowsStock(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product USD 1']);
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // 100 + 50 received, 10 sold by the fixture sale.
        $this->assertJsonContains(['@id' => $iri, 'remainingQty' => '140.000']);
    }

    public function testSuccessFilterProductsByName(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/products?name=UZS'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(['Test Product UZS 1'], array_column($response->toArray()['member'], 'name'));
    }

    public function testSuccessFilterProductsByCategory(): void
    {
        $categoryIri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/products?category.id=' . basename($categoryIri)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $names = array_column($response->toArray()['member'], 'name');
        $this->assertContains('Test Product USD 1', $names);
        $this->assertNotContains('Test Product UZS 1', $names);
    }

    /** "Test Product No Stock" was never received, so it is below its minStock. */
    public function testSuccessFilterLowStockProducts(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/products?lowStock=true'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $names = array_column($response->toArray()['member'], 'name');
        $this->assertContains('Test Product No Stock', $names);
        $this->assertNotContains('Test Product USD 1', $names);
    }

    public function testIncorrectGetProductCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/products');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
