<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPaymentAllocation;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends SupplierPaymentTestCase
{
    public function testSuccessDeleteFromDraft(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'USD'),
            '200.00'
        );
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');

        $admin->request(Request::METHOD_DELETE, $allocation['@id']);

        $this->assertStatus(Response::HTTP_NO_CONTENT, $admin);
    }

    public function testIncorrectDeleteFromPosted(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $admin->request(Request::METHOD_DELETE, $allocation['@id']);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }
}
