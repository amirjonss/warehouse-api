<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Receipt;

use App\Entity\Supplier;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateReceipt(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/receipts',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        // A fresh receipt is always a zero-total draft; the number is generated server side.
        $this->assertSame('draft', $data['status']);
        $this->assertSame(0.0, (float) $data['totalUsd']);
        $this->assertSame(0.0, (float) $data['totalUzs']);
        $this->assertStringStartsWith('RC-', $data['number']);
        $this->assertStringStartsWith('2026-08-01', $data['docDate']);
    }

    /** note is optional; omitting it must not blow up the create action. */
    public function testSuccessCreateReceiptWithoutNote(): void
    {
        $payload = $this->payload();
        unset($payload['note']);

        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/receipts',
            ['body' => json_encode($payload)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testIncorrectCreateReceiptByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/receipts',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectCreateReceiptAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/receipts',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function payload(): array
    {
        return [
            'docDate' => '2026-08-01',
            'supplier' => $this->findIriBy(Supplier::class, ['name' => 'Test Supplier 1']),
            'note' => '',
        ];
    }
}
