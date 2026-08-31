<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierDebt;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a receipt is what opens the obligation to the supplier — the mirror of a sale
 * opening a customer's debt.
 */
class GetApiTest extends SupplierPaymentTestCase
{
    public function testSuccessPostingAReceiptCreatesAPayable(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $debts = $this->supplierDebts($admin, $this->receiptIri('RC-00001'));

        $this->assertCount(1, $debts);
        $this->assertSame(200.0, (float) $debts[0]['amount']);
        $this->assertSame('USD', $debts[0]['currency']);
        $this->assertSame('receipt', $debts[0]['docType']);
    }

    public function testSuccessPayableIsWrittenPerCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $uzsDebts = $this->supplierDebts($admin, $this->receiptIri('RC-00003'));
        $this->assertCount(1, $uzsDebts);
        $this->assertSame('UZS', $uzsDebts[0]['currency']);
        $this->assertSame(8000000.0, (float) $uzsDebts[0]['amount']);
    }

    public function testSuccessSupplierTotalsFollowTheJournal(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        // RC-00001 (200) plus RC-00002 (125 + 320).
        $this->assertSame(645.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
        $this->assertSame(0.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUzs']);
        $this->assertSame(8000000.0, (float) $this->supplier($admin, self::UZS_SUPPLIER)['debtUzs']);
    }

    public function testSuccessCancellingAnUnpaidReceiptReversesThePayable(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $receiptIri = $this->createDraftReceipt($admin, self::SUPPLIER);
        $this->addReceiptItem($admin, $receiptIri, 'Test Product USD 1', '10.000', '3.00');
        $this->changeStatus($admin, $receiptIri, 'posted');
        $this->assertSame(675.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);

        $this->changeStatus($admin, $receiptIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $admin);

        // Append-only: two rows netting to zero, and the running total is back.
        $debts = $this->supplierDebts($admin, $receiptIri);
        $this->assertCount(2, $debts);
        $this->assertSame(0.0, array_sum(array_map(static fn (array $d): float => (float) $d['amount'], $debts)));
        $this->assertSame(645.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
    }

    public function testIncorrectReadPayablesAsSales(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/supplier_debts');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
