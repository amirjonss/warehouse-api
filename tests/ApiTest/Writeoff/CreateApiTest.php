<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Writeoff;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateWriteoff(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/writeoffs',
            ['body' => json_encode(['docDate' => '2026-08-03', 'reason' => 'Expired'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('draft', $data['status']);
        $this->assertSame('Expired', $data['reason']);
        $this->assertStringStartsWith('WR-', $data['number']);
    }

    public function testIncorrectCreateWriteoffByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/writeoffs',
            ['body' => json_encode(['docDate' => '2026-08-03', 'reason' => 'Expired'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectCreateWriteoffAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/writeoffs',
            ['body' => json_encode(['docDate' => '2026-08-03', 'reason' => 'Expired'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
