<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Wallet;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Card and transfer money never physically reaches the seller, so it bypasses the shift
 * entirely and lands on the company account the moment the payment is posted. Before the
 * treasury existed such a payment closed the debt and the money existed nowhere at all.
 */
class NonCashInflowApiTest extends WalletTestCase
{
    public function testSuccessCardPaymentCreditsTheCardAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $cardIri = $this->accountIri('card', 'UZS');

        $this->openCashSession($seller);
        $this->collect($seller, '750000.00', 'UZS', 'card');

        $this->assertSame(750000.0, (float) $this->accountBalance($owner, $cardIri));
        $entries = $this->accountEntries($owner, $cardIri);
        $this->assertCount(1, $entries);
        $this->assertSame('collect', $entries[0]['kind']);
    }

    public function testSuccessTransferPaymentCreditsTheBankAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $this->openCashSession($seller);
        $this->collect($seller, '900000.00', 'UZS', 'transfer');

        $this->assertSame(900000.0, (float) $this->accountBalance($owner, $this->accountIri('bank', 'UZS')));
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $this->accountIri('card', 'UZS')));
    }

    /** The case the old early-return silently dropped: no shift, and the money vanished. */
    public function testSuccessNonCashCreditsTheAccountWithoutAnOpenSession(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $this->collect($seller, '400000.00', 'UZS', 'transfer');

        $this->assertSame(400000.0, (float) $this->accountBalance($owner, $this->accountIri('bank', 'UZS')));
    }

    public function testSuccessNonCashDoesNotTouchTheCashSessionBalance(): void
    {
        $seller = $this->createSalesClientWithCredentials();

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '750000.00', 'UZS', 'card');

        $this->assertSame(0.0, (float) $this->session($seller, $sessionIri)['balanceUzs']);
        $this->assertCount(0, $this->cashEntries($seller, $sessionIri));
    }

    public function testSuccessCancellingACardPaymentDebitsTheCardAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $cardIri = $this->accountIri('card', 'UZS');

        $this->openCashSession($seller);
        $paymentIri = $this->collect($seller, '750000.00', 'UZS', 'card');

        $this->changeStatus($seller, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $seller);

        $this->assertSame(0.0, (float) $this->accountBalance($owner, $cardIri));
        // Append-only: the reversal is a second row, not a deletion.
        $this->assertCount(2, $this->accountEntries($owner, $cardIri));
        $this->assertAccountJournalMatchesBalance($owner, $cardIri);
    }

    public function testSuccessCancellingACardPaymentTwiceAddsNoSecondRow(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $cardIri = $this->accountIri('card', 'UZS');

        $paymentIri = $this->collect($seller, '750000.00', 'UZS', 'card');
        $this->changeStatus($seller, $paymentIri, 'cancelled');
        $this->changeStatus($seller, $paymentIri, 'cancelled');

        $this->assertCount(2, $this->accountEntries($owner, $cardIri));
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $cardIri));
    }

    /**
     * Once the card money has been moved to the bank, taking it back out would drive the
     * card account negative — and a negative treasury balance means the system is lying.
     */
    public function testIncorrectCancelACardPaymentAfterTheMoneyWasTransferredOut(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $paymentIri = $this->collect($seller, '750000.00', 'UZS', 'card');
        $this->postTransfer($owner, $this->accountIri('card', 'UZS'), $this->accountIri('bank', 'UZS'), '750000.00');

        $this->changeStatus($seller, $paymentIri, 'cancelled');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $seller);
        $this->assertStringContainsString('уже израсходованы или переведены', $this->detail($seller));
        $this->assertSame('posted', $seller->request(Request::METHOD_GET, $paymentIri)->toArray()['status']);
    }

    public function testIncorrectUsdCardPayment(): void
    {
        $seller = $this->createSalesClientWithCredentials();

        $seller->request(Request::METHOD_POST, '/api/payments', [
            'body' => json_encode([
                'docDate' => '2026-08-03',
                'client' => $this->clientIri(self::CUSTOMER),
                'amount' => '50.00',
                'currency' => 'USD',
                'method' => 'card',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $seller);
        $this->assertStringContainsString('доллары принимаются только наличными', $this->detail($seller));
    }

    public function testIncorrectUsdTransferPayment(): void
    {
        $seller = $this->createSalesClientWithCredentials();

        $seller->request(Request::METHOD_POST, '/api/payments', [
            'body' => json_encode([
                'docDate' => '2026-08-03',
                'client' => $this->clientIri(self::CUSTOMER),
                'amount' => '50.00',
                'currency' => 'USD',
                'method' => 'transfer',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $seller);
    }

    public function testSuccessUsdCashPaymentIsStillAccepted(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);

        $this->collect($seller, '50.00', 'USD', 'cash');

        $this->assertSame(50.0, (float) $this->session($seller, $sessionIri)['balanceUsd']);
    }
}
