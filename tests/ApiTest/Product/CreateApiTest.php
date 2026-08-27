<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateProduct(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            'name' => 'New Product',
            'currency' => 'USD',
            'unit' => 'kg',
            'remainingQty' => '0.000',
        ]);
    }

    /** currency and unit are backed enums; anything outside them is rejected while deserializing. */
    public function testIncorrectCreateProductWithUnknownCurrency(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode(['currency' => 'EUR'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIncorrectCreateProductWithUnknownUnit(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode(['unit' => 'tonne'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIncorrectCreateProductWithBlankName(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode(['name' => ''] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'name']]]);
    }

    /** Required fields are validated, so an empty payload never reaches the database. */
    public function testIncorrectCreateProductWithoutRequiredFields(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode([])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $paths = array_column($this->getResponseViolations(), 'propertyPath');
        $this->assertContains('name', $paths);
        $this->assertContains('category', $paths);
        $this->assertContains('currency', $paths);
        $this->assertContains('unit', $paths);
    }

    public function testIncorrectCreateProductWithNegativeMinStock(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode(['minStock' => '-1.000'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'minStock']]]);
    }

    public function testIncorrectCreateProductByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/products',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function payload(): array
    {
        return [
            'name' => 'New Product',
            'category' => $this->findIriBy(Category::class, ['name' => 'Test Category 1']),
            'currency' => 'USD',
            'unit' => 'kg',
            'minStock' => '50.000',
            'priceUsd' => '45.00',
            'isActive' => true,
        ];
    }
}
