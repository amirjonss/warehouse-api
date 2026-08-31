<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPaymentAllocation;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which receipt a payment closes, and by how much. amountSpent is money in the payment's
 * currency; the allocation's currency is that of the debt being closed, and when the two
 * differ the agreed rate bridges them.
 */
class CreateApiTest extends SupplierPaymentTestCase
{
    public function testSuccessAllocateSameCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'USD'),
            '200.00'
        );

        $allocation = $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame(200.0, (float) $allocation['amountClosed']);
        $this->assertSame(0.0, (float) $allocation['roundingWriteOff']);
    }

    public function testSuccessAllocateCrossCurrencyAtAnAgreedRate(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('bank', 'UZS'),
            '2400000.00',
            'UZS'
        );

        $allocation = $this->allocateToReceipt(
            $admin,
            $paymentIri,
            $this->receiptIri('RC-00001'),
            '2400000.00',
            'USD',
            '12000'
        );

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame(200.0, (float) $allocation['amountClosed']);
    }

    public function testIncorrectAllocateWithoutPayRateAcrossCurrencies(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('bank', 'UZS'),
            '2400000.00',
            'UZS'
        );

        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '2400000.00', 'USD');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectAllocateToADraftReceipt(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $draftReceipt = $this->createDraftReceipt($admin, self::SUPPLIER);
        $this->addReceiptItem($admin, $draftReceipt, 'Test Product USD 1', '5.000', '3.00');

        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'USD'),
            '15.00'
        );
        $this->allocateToReceipt($admin, $paymentIri, $draftReceipt, '15.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectAllocateToAReceiptOfAnotherSupplier(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'UZS'),
            '100000.00',
            'UZS'
        );

        // RC-00003 belongs to Test Supplier 2.
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00003'), '100000.00', 'UZS');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectAllocateToAPostedPayment(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00002'), '10.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }
}
