<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Supplier;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    private const PAYLOAD = [
        'name' => 'New Supplier',
        'contact' => 'John Smith',
        'phone' => '+998907654321',
        'address' => 'Tashkent, Yunusabad',
        'isActive' => true,
    ];

    public function testSuccessCreateSupplier(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/suppliers',
            ['body' => json_encode(self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains(['name' => 'New Supplier', 'isActive' => true]);
    }

    public function testIncorrectCreateSupplierWithBlankName(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/suppliers',
            ['body' => json_encode(['name' => ''] + self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'name']]]);
    }

    public function testIncorrectCreateSupplierWithoutRequiredFields(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/suppliers',
            ['body' => json_encode([])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateSupplierByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/suppliers',
            ['body' => json_encode(self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectCreateSupplierAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/suppliers',
            ['body' => json_encode(self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
