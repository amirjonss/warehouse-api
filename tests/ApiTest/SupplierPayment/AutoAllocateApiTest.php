<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\SupplierPayment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One call spreads the money over the supplier's unpaid receipts, oldest first, and
 * posts the payment — the mirror of /payments/{id}/auto_allocate.
 */
class AutoAllocateApiTest extends SupplierPaymentTestCase
{
    public function testSuccessAutoAllocateClosesOldestReceiptsFirst(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        // 300 covers RC-00001 (200) in full and 100 of RC-00002.
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '300.00');
        $this->autoAllocate($admin, $paymentIri);
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $this->receiptIri('RC-00001'))['outstandingUsd']);
        $this->assertSame(345.0, (float) $this->receipt($admin, $this->receiptIri('RC-00002'))['outstandingUsd']);
    }

    public function testSuccessAutoAllocatePostsInOneCall(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->autoAllocate($admin, $paymentIri);

        $payment = $admin->request(Request::METHOD_GET, $paymentIri)->toArray();
        $this->assertSame('posted', $payment['status']);
        $this->assertNotNull($payment['postedAt']);
        $this->assertSame(800.0, (float) $this->accountBalance($admin, $cashUsd));
    }

    public function testSuccessAutoAllocateStaysWithinThePaymentCurrency(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUzs = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $cashUzs, '9000000.00');

        // Supplier 2 owes sums only; a sum payment must find that receipt.
        $paymentIri = $this->createDraftSupplierPayment(
            $admin,
            self::UZS_SUPPLIER,
            $cashUzs,
            '8000000.00',
            'UZS'
        );
        $this->autoAllocate($admin, $paymentIri);
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(0.0, (float) $this->receipt($admin, $this->receiptIri('RC-00003'))['outstandingUzs']);
    }

    public function testSuccessAutoAllocatePartialPaymentLeavesTheRest(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '50.00');
        $this->autoAllocate($admin, $paymentIri);

        $this->assertSame(150.0, (float) $this->receipt($admin, $this->receiptIri('RC-00001'))['outstandingUsd']);
        $this->assertSame(595.0, (float) $this->supplier($admin, self::SUPPLIER)['debtUsd']);
    }

    public function testIncorrectAutoAllocateOnAPostedPayment(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '1000.00');

        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '200.00');
        $this->autoAllocate($admin, $paymentIri);

        $this->autoAllocate($admin, $paymentIri);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('черновика', $this->detail($admin));
    }

    public function testIncorrectAutoAllocateExceedingTheDebt(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUsd = $this->accountIri('cash', 'USD');
        $this->fund($admin, $cashUsd, '2000.00');

        // The supplier is owed 645 in total.
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUsd, '700.00');
        $this->autoAllocate($admin, $paymentIri);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('превышает долг поставщику', $this->detail($admin));
    }

    public function testIncorrectAutoAllocateWithNothingOutstanding(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashUzs = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $cashUzs, '1000000.00');

        // Supplier 1 owes dollars, not sums.
        $paymentIri = $this->createDraftSupplierPayment($admin, self::SUPPLIER, $cashUzs, '500000.00', 'UZS');
        $this->autoAllocate($admin, $paymentIri);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('нет неоплаченных приходов', $this->detail($admin));
    }

    private function autoAllocate(\ApiPlatform\Symfony\Bundle\Test\Client $admin, string $paymentIri): void
    {
        $admin->request(Request::METHOD_POST, $paymentIri . '/auto_allocate', ['body' => json_encode([])]);
    }
}
