<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPaymentAllocation;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends SupplierPaymentTestCase
{
    public function testSuccessUpdateRecomputesAmountClosed(): void
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

        $updated = $admin->request(Request::METHOD_PATCH, $allocation['@id'], [
            'headers' => ['content-type' => self::MERGE_PATCH],
            'body' => json_encode(['amountSpent' => '1200000.00']),
        ])->toArray();

        $this->assertSame(100.0, (float) $updated['amountClosed']);
    }

    public function testIncorrectUpdateOnAPostedPayment(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $allocation = $this->allocateToReceipt($admin, $paymentIri, $this->receiptIri('RC-00001'), '200.00');
        $this->changeStatus($admin, $paymentIri, 'posted');

        $admin->request(Request::METHOD_PATCH, $allocation['@id'], [
            'headers' => ['content-type' => self::MERGE_PATCH],
            'body' => json_encode(['amountSpent' => '10.00']),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }
}
