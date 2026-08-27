<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Payment;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    private function payload(): array
    {
        return [
            'docDate' => '2026-08-03',
            'client' => $this->clientIri('Test Client 2'),
            'amount' => '50.00',
            'currency' => 'USD',
            'method' => 'cash',
        ];
    }

    public function testSuccessCreatePayment(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('draft', $data['status']);
        $this->assertSame('50.00', $data['amount']);
        $this->assertSame('USD', $data['currency']);
        $this->assertSame('cash', $data['method']);
        $this->assertStringStartsWith('PY-', $data['number']);
    }

    /** rate and rateKind describe one conversion and must be supplied together. */
    public function testIncorrectCreatePaymentWithRateButNoRateKind(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode(['rate' => '12500'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['message' => 'rate and rateKind must be filled together']]]);
    }

    public function testIncorrectCreatePaymentWithRateKindButNoRate(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode(['rateKind' => 'sell'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSuccessCreatePaymentWithRateAndRateKind(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode(['rate' => '12500', 'rateKind' => 'sell'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testIncorrectCreatePaymentWithUnknownMethod(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode(['method' => 'bitcoin'] + $this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIncorrectCreatePaymentAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/payments',
            ['body' => json_encode($this->payload())]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
