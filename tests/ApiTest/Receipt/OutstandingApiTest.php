<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Receipt;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * What is still owed on a receipt is not stored: it is computed per page from the
 * supplier_debts ledger, exactly as a sale's outstanding comes from debts.
 */
class OutstandingApiTest extends SupplierPaymentTestCase
{
    public function testSuccessDraftReceiptHasZeroOutstanding(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $receiptIri = $this->createDraftReceipt($admin, self::SUPPLIER);
        $this->addReceiptItem($admin, $receiptIri, 'Test Product USD 1', '10.000', '3.00');

        // Nothing is owed until the receipt is posted.
        $this->assertSame(0.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    public function testSuccessPostedReceiptShowsTheFullOutstanding(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $this->assertSame(200.0, (float) $this->receipt($admin, $this->receiptIri('RC-00001'))['outstandingUsd']);
        $this->assertSame(8000000.0, (float) $this->receipt($admin, $this->receiptIri('RC-00003'))['outstandingUzs']);
    }

    public function testSuccessPartiallyPaidReceiptShowsTheRemainder(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $receiptIri = $this->receiptIri('RC-00001');
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '75.00');
        $this->allocateToReceipt($admin, $paymentIri, $receiptIri, '75.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertSame(125.0, (float) $this->receipt($admin, $receiptIri)['outstandingUsd']);
    }

    public function testSuccessCollectionOutstandingIsFilledForEveryRow(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $receipts = $admin->request(Request::METHOD_GET, '/api/receipts?order[id]=asc')->toArray()['member'];

        foreach ($receipts as $receipt) {
            $this->assertArrayHasKey('outstandingUsd', $receipt);
            $this->assertArrayHasKey('outstandingUzs', $receipt);
        }

        $byNumber = [];
        foreach ($receipts as $receipt) {
            $byNumber[$receipt['number']] = $receipt;
        }
        $this->assertSame(200.0, (float) $byNumber['RC-00001']['outstandingUsd']);
        $this->assertSame(445.0, (float) $byNumber['RC-00002']['outstandingUsd']);
    }
}
