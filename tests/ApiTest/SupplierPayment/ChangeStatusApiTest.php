<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPayment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a supplier payment closes the payable and takes the money off the account it
 * names. The interesting case is cross-currency: the supplier agrees to accept sums for
 * a dollar invoice at a rate the two of them settled on.
 */
class ChangeStatusApiTest extends SupplierPaymentTestCase
{
    public function testSuccessPostClosesThePayableAndDebitsTheAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
        $this->assertSame(445.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
        $this->assertSame(800.0, (float) $this->accountBalance($admin, $cashUsd));
    }

    /** The owner's own case: 1000 USD of debt closed by a sum transfer at an agreed rate. */
    public function testSuccessPayAUsdDebtWithAUzsTransferAtAnAgreedRate(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $bank = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $bank, '5000000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        // 2 400 000 sums at 12 000 closes exactly 200 dollars.
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $bank, '2400000.00', 'UZS');
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '2400000.00', 'USD', '12000');
        $this->assertSame(200.0, (float) $allocation['amountClosed']);

        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
        $this->assertSame(2600000.0, (float) $this->accountBalance($admin, $bank));
    }

    public function testSuccessPayAUsdDebtWithUsdCash(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '500.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '120.00');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '120.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertSame(80.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    /**
     * 2 468 900 sums at 12 345 divides into 199.991…, which truncates to 199.99 and would
     * leave a kopeck on the receipt forever. It is written off instead.
     */
    public function testSuccessRoundingRemainderClosesTheReceiptExactly(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $bank = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $bank, '5000000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $bank, '2468900.00', 'UZS');
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '2468900.00', 'USD', '12345');
        $this->assertSame(199.99, (float) $allocation['amountClosed']);

        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
        $this->assertSame(0.01, (float) $admin->request(Request::METHOD_GET, $allocation['@id'])
            ->toArray()['roundingWriteOff']);
    }

    /** A kopeck of overpayment is the same artefact seen from the other side. */
    public function testSuccessOverpayWithinToleranceIsClampedToZero(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $bank = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $bank, '5000000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $bank, '2469150.00', 'UZS');
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '2469150.00', 'USD', '12345');
        $this->assertSame(200.01, (float) $allocation['amountClosed']);

        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
        $this->assertSame(-0.01, (float) $admin->request(Request::METHOD_GET, $allocation['@id'])
            ->toArray()['roundingWriteOff']);
    }

    /** A real shortfall is money, not rounding: the receipt stays open for the rest. */
    public function testIncorrectGapBeyondToleranceLeavesTheReceiptOpen(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $bank = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $bank, '5000000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $bank, '1200000.00', 'UZS');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '1200000.00', 'USD', '12000');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertSame(100.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    public function testIncorrectPostWithoutAllocations(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectPostNotFullyAllocated(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '150.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('распределите оставшиеся', $this->detail($admin));
    }

    public function testIncorrectPostExceedingThePayable(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '300.00');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '300.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('осталось только', $this->detail($admin));
        $this->assertSame(200.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    public function testIncorrectPostWithInsufficientAccountBalance(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '50.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('оплатить', $this->detail($admin));
        $this->assertSame(50.0, (float) $this->accountBalance($admin, $cashUsd));
    }

    public function testIncorrectMovePostedPaymentBackToDraft(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->postedPayment($admin, '200.00');

        $this->changeStatus($admin, $paymentIri, 'draft');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectPostCancelledPayment(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->postedPayment($admin, '200.00');

        $this->changeStatus($admin, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('создайте новый документ', $this->detail($admin));
    }

    public function testSuccessCancelReopensThePayableAndReturnsTheMoney(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->postedPayment($admin, '200.00');

        $this->changeStatus($admin, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(200.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
        $this->assertSame(645.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
        $this->assertSame(1000.0, (float) $this->accountBalance($admin, $cashUsd));
    }

    /** The written-off kopeck has to come back too, or the receipt cannot return to 200. */
    public function testSuccessCancelRestoresTheRoundingKopeckToo(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $bank = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $bank, '5000000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $bank, '2468900.00', 'UZS');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '2468900.00', 'USD', '12345');
        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);

        $this->changeStatus($admin, $paymentIri, 'cancelled');

        $this->assertSame(200.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    public function testSuccessAccountJournalMatchesBalanceAfterPostAndCancel(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $paymentIri = $this->postedPayment($admin, '200.00');
        $this->changeStatus($admin, $paymentIri, 'cancelled');

        $this->assertAccountJournalMatchesBalance($admin, $cashUsd);
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'USD'),
            '10.00'
        );

        $sales = $this->createSalesClientWithCredentials();
        $this->changeStatus($sales, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }

    /** Funds the USD cash account with 1000 and posts a payment of the given size. */
    private function postedPayment(\ApiPlatform\Symfony\Bundle\Test\Client $admin, string $amount): string
    {
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, $amount);
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), $amount);
        $this->changeStatus($admin, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        return $paymentIri;
    }
}
