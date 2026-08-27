<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\PaymentAllocation;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetAllocationCollection(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $this->allocate($client, $paymentIri, '50.00');

        $response = $client->request(
            Request::METHOD_GET,
            '/api/payment_allocations?payment=' . basename($paymentIri)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testIncorrectGetAllocationCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/payment_allocations');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
