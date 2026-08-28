<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\PaymentAllocation;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateAllocationRecalculatesAmountClosed(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $allocation = $this->allocate($client, $paymentIri, '50.00');

        $client->request(Request::METHOD_PATCH, $allocation['@id'], [
            'body' => json_encode(['amountSpent' => '30.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['amountSpent' => '30.00', 'amountClosed' => '30.00']);
    }

    public function testIncorrectUpdateAllocationWithNonPositiveAmount(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $allocation = $this->allocate($client, $paymentIri, '50.00');

        $client->request(Request::METHOD_PATCH, $allocation['@id'], [
            'body' => json_encode(['amountSpent' => '-1.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Once the payment is posted its allocations are frozen. */
    public function testIncorrectUpdateAllocationOfPostedPayment(): void
    {
        $client = $this->createSalesClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $allocation = $this->allocate($client, $paymentIri, '50.00');

        $this->changeStatus($client, $paymentIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(Request::METHOD_PATCH, $allocation['@id'], [
            'body' => json_encode(['amountSpent' => '30.00']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
