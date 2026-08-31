<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Supplier;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The supplier carries a denormalised running total, exactly as the client does, and
 * SupplierDebtFactory is its only writer.
 */
class DebtApiTest extends SupplierPaymentTestCase
{
    public function testSuccessSupplierExposesDenormalisedDebt(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $supplier = $this->supplier($admin, self::SUPPLIER);

        $this->assertSame(645.0, (float) $supplier['debtUsd']);
        $this->assertSame(0.0, (float) $supplier['debtUzs']);
    }

    public function testSuccessHasDebtFilterSelectsIndebtedSuppliers(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $indebted = $admin->request(Request::METHOD_GET, '/api/suppliers?hasDebt=true')->toArray()['member'];

        $names = array_column($indebted, 'name');
        $this->assertContains(self::SUPPLIER, $names);
        $this->assertContains(self::UZS_SUPPLIER, $names);
    }

    public function testSuccessOrderBySupplierDebt(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $suppliers = $admin->request(
            Request::METHOD_GET,
            '/api/suppliers?order[debtUsd]=desc'
        )->toArray()['member'];

        $this->assertSame(self::SUPPLIER, $suppliers[0]['name']);
    }

    public function testSuccessDebtFollowsAPostedPayment(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $this->assertSame(445.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
    }
}
