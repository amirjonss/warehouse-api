<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\SupplierPayment\SupplierPaymentTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The chain the treasury exists for, end to end: the seller collects cash, hands it over
 * at closing, the owner deposits it at the bank, and a day later pays a supplier out of
 * that account — settling a dollar invoice with sums at an agreed rate.
 *
 * Before this feature only the first step existed; everything after it left no trace of
 * where the money actually went.
 */
class MoneyChainApiTest extends SupplierPaymentTestCase
{
    public function testSuccessMoneyTravelsFromTheTillToTheSupplier(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $cashUzs = $this->accountIri('cash', 'UZS');
        $bank = $this->accountIri('bank', 'UZS');
        $receiptIri = $this->receiptIri('RC-00001');

        // 1. The seller sells for cash and closes the shift; the money reaches the treasury.
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '2400000.00', 'UZS');
        $this->closeSession($owner, $sessionIri, '0', '2400000.00');
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(2400000.0, (float) $this->accountBalance($owner, $cashUzs));

        // 2. The owner deposits it at the bank — a transfer, not a disappearance.
        $this->postTransfer($owner, $cashUzs, $bank, '2400000.00');

        $this->assertSame(0.0, (float) $this->accountBalance($owner, $cashUzs));
        $this->assertSame(2400000.0, (float) $this->accountBalance($owner, $bank));

        // 3. The supplier agrees to take those sums against a 200-dollar invoice at 12 000.
        $this->assertSame(200.0, (float) $this->receipt($owner, $receiptIri)['outstandingUsd']);

        $paymentIri = $this->createDraftSupplierPayment($owner, self::SUPPLIER, $bank, '2400000.00', 'UZS');
        $this->allocateToReceipt($owner, $paymentIri, $receiptIri, '2400000.00', 'USD', '12000');
        $this->changeStatus($owner, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $owner);

        // 4. The invoice is settled and the bank account is empty again.
        $this->assertSame(0.0, (float) $this->receipt($owner, $receiptIri)['outstandingUsd']);
        $this->assertSame(445.0, (float) $this->supplier($owner, self::SUPPLIER)['debtUsd']);
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $bank));

        // 5. Every ledger still adds up to its denormalised balance.
        $this->assertAccountJournalMatchesBalance($owner, $cashUzs);
        $this->assertAccountJournalMatchesBalance($owner, $bank);
        $this->assertCashJournalMatchesBalance($seller, $sessionIri);

        // 6. And the company-wide figure is the sum of the accounts, which is now zero.
        $summary = $owner->request(Request::METHOD_POST, '/api/cash_accounts/summary', [
            'body' => json_encode([]),
        ])->toArray();
        $this->assertSame(0.0, (float) $summary['totalUzs']);
    }

    /**
     * The same chain run backwards. Every step is reversible on its own, and the money
     * ends up exactly where it started.
     */
    public function testSuccessCancellingWalksTheChainBack(): void
    {
        $owner = $this->createAdminClientWithCredentials();
        $cashUzs = $this->accountIri('cash', 'UZS');
        $bank = $this->accountIri('bank', 'UZS');
        $receiptIri = $this->receiptIri('RC-00001');

        $this->fund($owner, $cashUzs, '2400000.00');
        $transferIri = $this->postTransfer($owner, $cashUzs, $bank, '2400000.00');

        $paymentIri = $this->createDraftSupplierPayment($owner, self::SUPPLIER, $bank, '2400000.00', 'UZS');
        $this->allocateToReceipt($owner, $paymentIri, $receiptIri, '2400000.00', 'USD', '12000');
        $this->changeStatus($owner, $paymentIri, 'posted');

        $this->changeStatus($owner, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $owner);
        $this->changeStatus($owner, $transferIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $owner);

        $this->assertSame(2400000.0, (float) $this->accountBalance($owner, $cashUzs));
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $bank));
        $this->assertSame(200.0, (float) $this->receipt($owner, $receiptIri)['outstandingUsd']);
        $this->assertAccountJournalMatchesBalance($owner, $cashUzs);
        $this->assertAccountJournalMatchesBalance($owner, $bank);
    }
}
