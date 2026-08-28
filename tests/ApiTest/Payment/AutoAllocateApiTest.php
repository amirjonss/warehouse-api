<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Payment;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/payments/{id}/auto_allocate takes an empty body (deserialize: false),
 * spreads the payment over the client's outstanding sales oldest-first and posts it
 * in a single call.
 */
class AutoAllocateApiTest extends BaseApiTestCase
{
    public function testSuccessAutoAllocateClosesTheOldestDebtFirst(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 2';

        // A second sale for the same client, dated after the fixture one: 5 x 4.00 = 20.00 USD.
        $newerSaleIri = $this->postSale($client, $customer, '5.000', '4.00');

        $paymentIri = $this->createDraftPayment($client, $customer, '55.00');
        $this->autoAllocate($client, $paymentIri);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // 50.00 went to the older fixture sale, the remaining 5.00 to the newer one.
        $allocations = $client->request(
            Request::METHOD_GET,
            '/api/payment_allocations?payment=' . basename($paymentIri)
        )->toArray()['member'];
        $this->assertCount(2, $allocations);

        $newer = $client->request(Request::METHOD_GET, $newerSaleIri)->toArray();
        $this->assertSame(15.0, (float) $newer['outstandingUsd']);

        // The client is left owing only what the payment could not cover.
        $customerData = $client->request(Request::METHOD_GET, $this->clientIri($customer))->toArray();
        $this->assertSame(15.0, (float) $customerData['debtUsd']);
    }

    /** Auto-allocation posts the payment itself, no separate change_status call. */
    public function testAutoAllocatePostsThePayment(): void
    {
        $client = $this->createSalesClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $this->autoAllocate($client, $paymentIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['status' => 'posted']);
    }

    public function testIncorrectAutoAllocateMoreThanTheClientOwes(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '500.00');

        $this->autoAllocate($client, $paymentIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Auto-allocation only works within the payment's own currency. */
    public function testIncorrectAutoAllocateWhenClientHasNoDebtInThatCurrency(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '600000.00', 'UZS');

        $this->autoAllocate($client, $paymentIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectAutoAllocateForClientWithoutDebt(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 1', '10.00');

        $this->autoAllocate($client, $paymentIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectAutoAllocateAnonymously(): void
    {
        $paymentIri = $this->createDraftPayment($this->createSalesClientWithCredentials(), 'Test Client 2', '50.00');

        $this->autoAllocate($this->createAnonymousClient(), $paymentIri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function autoAllocate(Client $client, string $paymentIri): void
    {
        $client->request(Request::METHOD_POST, $paymentIri . '/auto_allocate', [
            'headers' => ['content-type' => self::JSON_LD],
        ]);
    }

    private function postSale(Client $client, string $customer, string $quantity, string $price): string
    {
        $saleIri = $this->createDraftSale($client, $customer, '2026-08-20');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', $quantity, $price);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return $saleIri;
    }
}
