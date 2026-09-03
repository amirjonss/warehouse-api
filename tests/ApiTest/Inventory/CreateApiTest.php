<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Inventory;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateInventoryAsSales(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/inventories',
            ['body' => json_encode(['docDate' => '2026-08-03', 'note' => 'Плановый пересчёт'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('draft', $data['status']);
        $this->assertSame('Плановый пересчёт', $data['note']);
        $this->assertStringStartsWith('INV-', $data['number']);
        $this->assertNull($data['postedAt']);
    }

    /** The scope is recorded so a later reader can tell "not counted" from "counted, zero". */
    public function testSuccessCreateInventoryWithCategoryScope(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $categoryIri = $this->categoryIri('Test Category 1');

        $response = $client->request(
            Request::METHOD_POST,
            '/api/inventories',
            ['body' => json_encode(['docDate' => '2026-08-03', 'category' => $categoryIri])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('Test Category 1', $response->toArray()['category']['name']);
    }

    public function testSuccessCreateInventoryAsAdmin(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/inventories',
            ['body' => json_encode(['docDate' => '2026-08-03'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testIncorrectCreateInventoryAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/inventories',
            ['body' => json_encode(['docDate' => '2026-08-03'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
