<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Payment;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetPaymentCollection(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $response = $client->request(Request::METHOD_GET, '/api/payments');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessFilterPaymentsByClient(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $response = $client->request(
            Request::METHOD_GET,
            '/api/payments?client=' . basename($this->clientIri('Test Client 1'))
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(0, $response->toArray()['totalItems']);
    }

    public function testIncorrectGetPaymentCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/payments');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
