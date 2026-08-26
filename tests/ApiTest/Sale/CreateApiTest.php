<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Sale;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateSale(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/sales',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('draft', $data['status']);
        $this->assertStringStartsWith('SL-', $data['number']);
        $this->assertSame(0.0, (float) $data['totalUsd']);
        $this->assertStringStartsWith('2026-08-02', $data['docDate']);
        $this->assertNull($data['postedAt'] ?? null);
    }

    /** soldBy is taken from the token, never from the payload. */
    public function testSoldByIsTheAuthenticatedUser(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $me = $client->request(Request::METHOD_POST, '/api/users/about_me')->toArray();
        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();

        $this->assertSame($me['@id'], $sale['soldBy']['@id'] ?? $sale['soldBy']);
    }

    public function testSuccessCreateSaleWithoutNote(): void
    {
        $payload = $this->payload();
        unset($payload['note']);

        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/sales',
            ['body' => json_encode($payload)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testIncorrectCreateSaleAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/sales',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function payload(): array
    {
        return [
            'docDate' => '2026-08-02',
            'customer' => $this->clientIri('Test Client 1'),
            'note' => '',
        ];
    }
}
