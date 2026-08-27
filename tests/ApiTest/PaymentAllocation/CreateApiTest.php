<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\PaymentAllocation;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessCreateAllocationInTheSameCurrency(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $response = $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '50.00',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // Same currency on both sides, so nothing is converted.
        $this->assertSame('50.00', $response->toArray()['amountClosed']);
    }

    /** Crossing currencies needs an explicit payRate; without it the allocation is refused. */
    public function testIncorrectCreateCrossCurrencyAllocationWithoutPayRate(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '600000.00', 'UZS');

        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '600000.00',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSuccessCreateCrossCurrencyAllocationWithPayRate(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '600000.00', 'UZS');

        $response = $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '600000.00',
                'payRate' => '12000',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // 600000 UZS at 12000 closes 50 USD of the debt.
        $this->assertSame(50.0, (float) $response->toArray()['amountClosed']);
    }

    public function testIncorrectCreateAllocationWithNonPositiveAmount(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 2', '50.00');

        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '0',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** A payment can only be spread over sales of the same client. */
    public function testIncorrectCreateAllocationForAnotherClientsSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 1', '50.00');

        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '50.00',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Only posted sales carry debt, so a draft sale cannot be paid. */
    public function testIncorrectCreateAllocationForDraftSale(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $paymentIri = $this->createDraftPayment($client, 'Test Client 3', '50.00');

        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00002']),
                'currency' => 'USD',
                'amountSpent' => '50.00',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateAllocationAnonymously(): void
    {
        $paymentIri = $this->createDraftPayment($this->createSalesClientWithCredentials(), 'Test Client 2', '50.00');

        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => 'SL-00001']),
                'currency' => 'USD',
                'amountSpent' => '50.00',
                'isRounding' => false,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
