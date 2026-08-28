<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Payment;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a payment writes negative debt entries, which is what actually reduces the
 * sale's outstanding balance and the client's running debt.
 */
class ChangeStatusApiTest extends BaseApiTestCase
{
    public function testSuccessPostPaymentClosesTheDebt(): void
    {
        $client = $this->createSalesClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $saleIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);
        $customerIri = $this->clientIri('Test Client 2');

        $this->assertSame(50.0, (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd']);

        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $this->allocate($client, $paymentIri, '50.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->changeStatus($client, $paymentIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['status' => 'posted']);
        $this->assertNotNull($client->request(Request::METHOD_GET, $paymentIri)->toArray()['postedAt']);

        // The sale is settled and the client owes nothing.
        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(0.0, (float) $sale['outstandingUsd']);

        $customer = $client->request(Request::METHOD_GET, $customerIri)->toArray();
        $this->assertSame(0.0, (float) $customer['debtUsd']);

        // A compensating negative debt entry was appended next to the original one.
        $debts = $client->request(Request::METHOD_GET, '/api/debts?sale=' . basename($saleIri))->toArray()['member'];
        $this->assertCount(2, $debts);
        $this->assertSame(0.0, array_sum(array_map(static fn (array $d): float => (float) $d['amount'], $debts)));
    }

    public function testSuccessPartialPaymentLeavesTheRest(): void
    {
        $client = $this->createSalesClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $saleIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '20.00');
        $this->allocate($client, $paymentIri, '20.00');
        $this->changeStatus($client, $paymentIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(30.0, (float) $sale['outstandingUsd']);
    }

    public function testIncorrectPostPaymentWithoutAllocations(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $this->changeStatus($client, $paymentIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** The allocations must add up to the payment amount exactly. */
    public function testIncorrectPostUnderAllocatedPayment(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');
        $this->allocate($client, $paymentIri, '20.00');

        $this->changeStatus($client, $paymentIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** A payment must never close more than the sale actually owes. */
    public function testIncorrectPostPaymentExceedingTheDebt(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '80.00');
        $this->allocate($client, $paymentIri, '80.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->changeStatus($client, $paymentIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectChangeStatusAnonymously(): void
    {
        $paymentIri = $this->createDraftPayment($this->createSalesClientWithCredentials(), 'Test Client 2', '50.00');

        $this->changeStatus($this->createAnonymousClient(), $paymentIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
