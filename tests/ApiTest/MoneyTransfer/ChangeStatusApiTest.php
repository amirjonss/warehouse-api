<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\MoneyTransfer;

use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a transfer is two journal rows, one on each account. Nothing is ever updated:
 * cancelling appends the mirror pair, so both sides stay evidence.
 */
class ChangeStatusApiTest extends WalletTestCase
{
    public function testSuccessPostCollectionMovesCashToTheBank(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $this->postTransfer($admin, $cashIri, $bankIri, '4000000.00');

        $this->assertSame(6000000.0, (float) $this->accountBalance($admin, $cashIri));
        $this->assertSame(4000000.0, (float) $this->accountBalance($admin, $bankIri));

        $out = $this->accountEntries($admin, $cashIri);
        $this->assertSame('transfer_out', $out[1]['kind']);
        $this->assertSame(-4000000.0, (float) $out[1]['amount']);
    }

    public function testSuccessPostExchangeConvertsUzsToUsd(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $uzsIri = $this->accountIri('cash', 'UZS');
        $usdIri = $this->accountIri('cash', 'USD');
        $this->fund($admin, $uzsIri, '12000000.00');

        $this->postTransfer($admin, $uzsIri, $usdIri, '12000000.00', '1000.00', '12000');

        $this->assertSame(0.0, (float) $this->accountBalance($admin, $uzsIri));
        $this->assertSame(1000.0, (float) $this->accountBalance($admin, $usdIri));
    }

    public function testSuccessAccountJournalMatchesBalanceAfterPost(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $this->postTransfer($admin, $cashIri, $bankIri, '2000000.00');
        $this->postTransfer($admin, $cashIri, $bankIri, '3000000.00');

        $this->assertAccountJournalMatchesBalance($admin, $cashIri);
        $this->assertAccountJournalMatchesBalance($admin, $bankIri);
    }

    public function testIncorrectPostWithInsufficientBalance(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $cashIri, '100000.00');

        $iri = $this->createDraftTransfer($admin, $cashIri, $this->accountIri('bank', 'UZS'), '500000.00');
        $this->changeStatus($admin, $iri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('списать', $this->detail($admin));
        $this->assertSame(100000.0, (float) $this->accountBalance($admin, $cashIri));
    }

    public function testPostingAnAlreadyPostedTransferIsANoop(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $iri = $this->postTransfer($admin, $cashIri, $bankIri, '1000000.00');
        $this->changeStatus($admin, $iri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(1000000.0, (float) $this->accountBalance($admin, $bankIri));
        $this->assertCount(1, $this->accountEntries($admin, $bankIri));
    }

    public function testIncorrectMovePostedTransferBackToDraft(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $iri = $this->postTransfer($admin, $cashIri, $this->accountIri('bank', 'UZS'), '1000000.00');
        $this->changeStatus($admin, $iri, 'draft');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectPostCancelledTransfer(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $iri = $this->postTransfer($admin, $cashIri, $bankIri, '1000000.00');
        $this->changeStatus($admin, $iri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->changeStatus($admin, $iri, 'posted');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('создайте новый документ', $this->detail($admin));

        // The reversal stands: nothing was written a second time.
        $this->assertSame(10000000.0, (float) $this->accountBalance($admin, $cashIri));
    }

    public function testSuccessCancelPostedTransferReturnsTheMoney(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $iri = $this->postTransfer($admin, $cashIri, $bankIri, '4000000.00');
        $this->changeStatus($admin, $iri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $admin);

        $this->assertSame(10000000.0, (float) $this->accountBalance($admin, $cashIri));
        $this->assertSame(0.0, (float) $this->accountBalance($admin, $bankIri));
        // Append-only on both sides: two rows each, netting to the original state.
        $this->assertCount(2, $this->accountEntries($admin, $bankIri));
        $this->assertAccountJournalMatchesBalance($admin, $bankIri);
    }

    public function testIncorrectCancelWhenTheReceivingAccountWasAlreadyEmptied(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $bankIri = $this->accountIri('bank', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');

        $iri = $this->postTransfer($admin, $cashIri, $bankIri, '4000000.00');
        // The money moved on to dollars; there is nothing left on the bank to take back.
        $this->postTransfer($admin, $bankIri, $this->accountIri('cash', 'USD'), '4000000.00', '320.00', '12500');

        $this->changeStatus($admin, $iri, 'cancelled');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertSame('posted', $admin->request(Request::METHOD_GET, $iri)->toArray()['status']);
    }

    public function testIncorrectChangeStatusByRole(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $iri = $this->createDraftTransfer($admin, $cashIri, $this->accountIri('bank', 'UZS'), '100.00');

        $sales = $this->createSalesClientWithCredentials();
        $this->changeStatus($sales, $iri, 'posted');

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }
}
