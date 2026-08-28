<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\PaymentAllocation;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteAllocationOfDraftPayment(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $allocation = $this->allocate($client, $paymentIri, '50.00');

        $client->request(Request::METHOD_DELETE, $allocation['@id']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $response = $client->request(
            Request::METHOD_GET,
            '/api/payment_allocations?payment=' . basename($paymentIri)
        );
        $this->assertSame(0, $response->toArray()['totalItems']);
    }

    public function testIncorrectDeleteAllocationOfPostedPayment(): void
    {
        $client = $this->createSalesClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $allocation = $this->allocate($client, $paymentIri, '50.00');

        $this->changeStatus($client, $paymentIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(Request::METHOD_DELETE, $allocation['@id']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
