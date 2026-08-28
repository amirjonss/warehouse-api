<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** The whole money side end to end: sell on credit, pay it off in instalments. */
class PaymentDebtFlowApiTest extends BaseApiTestCase
{
    public function testSellOnCreditThenSettleInTwoInstalments(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 1';
        $customerIri = $this->clientIri($customer);

        // 1. Sell 20 units at 6.00 -> 120.00 USD of debt.
        $saleIri = $this->createDraftSale($client, $customer);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(120.0, (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd']);

        // 2. First instalment of 50.00.
        $firstPayment = $this->createDraftPayment($client, $customer, '50.00');
        $this->allocatePartial($client, $firstPayment, $saleIri, '50.00');
        $this->changeStatus($client, $firstPayment, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertSame(70.0, (float) $client->request(Request::METHOD_GET, $saleIri)->toArray()['outstandingUsd']);
        $this->assertSame(70.0, (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd']);

        // 3. Paying more than what is left must be refused.
        $tooBig = $this->createDraftPayment($client, $customer, '80.00');
        $this->allocatePartial($client, $tooBig, $saleIri, '80.00');
        $this->changeStatus($client, $tooBig, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // 4. Settling the exact remainder closes the sale.
        $secondPayment = $this->createDraftPayment($client, $customer, '70.00');
        $this->allocatePartial($client, $secondPayment, $saleIri, '70.00');
        $this->changeStatus($client, $secondPayment, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertSame(0.0, (float) $client->request(Request::METHOD_GET, $saleIri)->toArray()['outstandingUsd']);
        $this->assertSame(0.0, (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd']);
    }

    /** The client balance must always equal the sum of their ledger rows. */
    public function testClientBalanceTracksTheLedgerThroughout(): void
    {
        $client = $this->createAdminClientWithCredentials();
        // Cash is accepted by a specific person: without an open session posting a payment
        // is refused with a 422, so the session is opened before the first posted.
        $this->openCashSession($client);
        $customer = 'Test Client 1';
        $customerIri = $this->clientIri($customer);

        $saleIri = $this->createDraftSale($client, $customer);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');

        $payment = $this->createDraftPayment($client, $customer, '50.00');
        $this->allocatePartial($client, $payment, $saleIri, '50.00');
        $this->changeStatus($client, $payment, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $debts = $client->request(Request::METHOD_GET, '/api/debts?sale=' . basename($saleIri))->toArray()['member'];
        $ledgerSum = array_sum(array_map(static fn (array $d): float => (float) $d['amount'], $debts));

        $this->assertSame(70.0, $ledgerSum);
        $this->assertSame($ledgerSum, (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd']);
    }

    private function allocatePartial(object $client, string $paymentIri, string $saleIri, string $amount): void
    {
        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $saleIri,
                'currency' => 'USD',
                'amountSpent' => $amount,
                'isRounding' => false,
            ]),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
