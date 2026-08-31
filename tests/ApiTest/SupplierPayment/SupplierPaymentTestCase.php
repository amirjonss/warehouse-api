<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPayment;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Receipt;
use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fixture payables, opened by StockFixtures posting its receipts:
 *   RC-00001  Test Supplier 1  200.00 USD
 *   RC-00002  Test Supplier 1  445.00 USD
 *   RC-00003  Test Supplier 2  8 000 000 UZS
 */
abstract class SupplierPaymentTestCase extends WalletTestCase
{
    protected const SUPPLIER = 'Test Supplier 1';
    protected const UZS_SUPPLIER = 'Test Supplier 2';

    protected function receiptIri(string $number): string
    {
        return $this->findIriBy(Receipt::class, ['number' => $number]);
    }

    protected function createDraftSupplierPayment(
        Client $admin,
        string $supplierName,
        string $accountIri,
        string $amount,
        string $currency = 'USD',
        string $docDate = '2026-08-20',
    ): string {
        return $this->createAndGetIri($admin, '/api/supplier_payments', [
            'docDate' => $docDate,
            'supplier' => $this->supplierIri($supplierName),
            'account' => $accountIri,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /** @return array the decoded response, so failures can be inspected */
    protected function allocateToReceipt(
        Client $admin,
        string $paymentIri,
        string $receiptIri,
        string $amountSpent,
        string $currency = 'USD',
        ?string $payRate = null,
    ): array {
        return $admin->request(Request::METHOD_POST, '/api/supplier_payment_allocations', [
            'body' => json_encode([
                'supplierPayment' => $paymentIri,
                'receipt' => $receiptIri,
                'currency' => $currency,
                'amountSpent' => $amountSpent,
                'payRate' => $payRate,
            ]),
        ])->toArray(false);
    }

    protected function receipt(Client $admin, string $receiptIri): array
    {
        return $admin->request(Request::METHOD_GET, $receiptIri)->toArray();
    }

    protected function supplier(Client $admin, string $supplierName): array
    {
        return $admin->request(Request::METHOD_GET, $this->supplierIri($supplierName))->toArray();
    }

    /** @return array<int, array<string, mixed>> payables of one receipt, oldest first */
    protected function supplierDebts(Client $admin, string $receiptIri): array
    {
        return $admin->request(
            Request::METHOD_GET,
            '/api/supplier_debts?receipt=' . basename($receiptIri) . '&order[id]=asc'
        )->toArray(false)['member'] ?? [];
    }
}
