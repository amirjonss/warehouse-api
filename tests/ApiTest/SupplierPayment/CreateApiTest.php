<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPayment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends SupplierPaymentTestCase
{
    public function testSuccessCreateDraft(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $iri = $this->createDraftSupplierPayment(
            $admin,
            self::SUPPLIER,
            $this->accountIri('cash', 'USD'),
            '200.00'
        );

        $payment = $admin->request(Request::METHOD_GET, $iri)->toArray();
        $this->assertSame('draft', $payment['status']);
        $this->assertSame('SP-00001', $payment['number']);
        $this->assertNull($payment['postedAt']);
    }

    /** Naming the account makes this a currency comparison instead of a routing table. */
    public function testIncorrectAccountCurrencyDiffersFromPaymentCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $admin->request(Request::METHOD_POST, '/api/supplier_payments', [
            'body' => json_encode([
                'docDate' => '2026-08-20',
                'supplier' => $this->supplierIri(self::SUPPLIER),
                'account' => $this->accountIri('bank', 'UZS'),
                'amount' => '200.00',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('ведётся в UZS', $this->detail($admin));
    }

    public function testIncorrectNonPositiveAmount(): void
    {
        $admin = $this->createAdminClientWithCredentials();

        $admin->request(Request::METHOD_POST, '/api/supplier_payments', [
            'body' => json_encode([
                'docDate' => '2026-08-20',
                'supplier' => $this->supplierIri(self::SUPPLIER),
                'account' => $this->accountIri('cash', 'USD'),
                'amount' => '0',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectCreateAsSales(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'USD');

        $sales = $this->createSalesClientWithCredentials();
        $sales->request(Request::METHOD_POST, '/api/supplier_payments', [
            'body' => json_encode([
                'docDate' => '2026-08-20',
                'supplier' => $this->supplierIri(self::SUPPLIER),
                'account' => $accountIri,
                'amount' => '10.00',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }
}
