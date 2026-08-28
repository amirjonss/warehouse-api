<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-allocation is the "client walks in and hands over cash" path: one call spreads
 * the money over their open sales oldest-first and posts the payment.
 */
class PaymentAutoAllocationApiTest extends BaseApiTestCase
{
    public function testMoneySpreadsAcrossThreeSalesOldestFirst(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 1';

        // Three sales of 60.00 USD each, deliberately created out of date order.
        $middle = $this->postSale($client, $customer, '2026-08-15', '10.000', '6.00');
        $oldest = $this->postSale($client, $customer, '2026-08-05', '10.000', '6.00');
        $newest = $this->postSale($client, $customer, '2026-08-25', '10.000', '6.00');

        // 150 covers the oldest two in full and 30 of the third.
        $paymentIri = $this->createDraftPayment($client, $customer, '150.00');
        $client->request(Request::METHOD_POST, $paymentIri . '/auto_allocate', [
            'headers' => ['content-type' => self::JSON_LD],
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertSame(0.0, $this->outstanding($client, $oldest));
        $this->assertSame(0.0, $this->outstanding($client, $middle));
        $this->assertSame(30.0, $this->outstanding($client, $newest));

        $customerData = $client->request(Request::METHOD_GET, $this->clientIri($customer))->toArray();
        $this->assertSame(30.0, (float) $customerData['debtUsd']);
    }

    public function testAutoAllocationIsIdempotentlyRefusedOnceThereIsNoDebtLeft(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 1';

        $saleIri = $this->postSale($client, $customer, '2026-08-15', '10.000', '6.00');

        $first = $this->createDraftPayment($client, $customer, '60.00');
        $client->request(Request::METHOD_POST, $first . '/auto_allocate', ['headers' => ['content-type' => self::JSON_LD]]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(0.0, $this->outstanding($client, $saleIri));

        // The debt is gone, so a second payment has nothing to attach to.
        $second = $this->createDraftPayment($client, $customer, '10.00');
        $client->request(Request::METHOD_POST, $second . '/auto_allocate', ['headers' => ['content-type' => self::JSON_LD]]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** UZS debt is invisible to a USD payment and vice versa. */
    public function testAutoAllocationStaysWithinItsOwnCurrency(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 1';

        // A UZS-denominated sale, using UZS-costed stock so no rate is needed.
        $saleIri = $this->createDraftSale($client, $customer, '2026-08-15');
        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '10.000', '60000.00', 'UZS', null);
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // A USD payment finds no USD debt at all.
        $usdPayment = $this->createDraftPayment($client, $customer, '10.00');
        $client->request(Request::METHOD_POST, $usdPayment . '/auto_allocate', ['headers' => ['content-type' => self::JSON_LD]]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // The matching UZS payment settles it.
        $uzsPayment = $this->createDraftPayment($client, $customer, '600000.00', 'UZS');
        $client->request(Request::METHOD_POST, $uzsPayment . '/auto_allocate', ['headers' => ['content-type' => self::JSON_LD]]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(0.0, (float) $sale['outstandingUzs']);
    }

    private function outstanding(Client $client, string $saleIri): float
    {
        return (float) $client->request(Request::METHOD_GET, $saleIri)->toArray()['outstandingUsd'];
    }

    private function postSale(Client $client, string $customer, string $docDate, string $quantity, string $price): string
    {
        $saleIri = $this->createDraftSale($client, $customer, $docDate);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', $quantity, $price);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return $saleIri;
    }
}
