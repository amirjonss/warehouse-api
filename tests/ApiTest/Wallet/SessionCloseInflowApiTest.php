<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Wallet;

use Symfony\Component\HttpFoundation\Response;

/**
 * At closing the owner counts the money themselves, so the closing handover is created
 * already confirmed and reaches the treasury at once. A shortage writes nothing there:
 * that money never arrived, which is the whole point of the shortage row.
 */
class SessionCloseInflowApiTest extends WalletTestCase
{
    public function testSuccessClosingHandoverCreditsTheCashAccountImmediately(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'USD');

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $this->closeSession($owner, $sessionIri, '50.00', '0');
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(50.0, (float) $this->accountBalance($owner, $accountIri));
        $entries = $this->accountEntries($owner, $accountIri);
        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Сдача при закрытии смены', (string) $entries[0]['note']);
    }

    public function testSuccessShortageDoesNotCreditTheAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'USD');

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        // The seller is 20 short: only what actually arrived reaches the treasury.
        $this->closeSession($owner, $sessionIri, '30.00', '0');
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(30.0, (float) $this->accountBalance($owner, $accountIri));
        $this->assertCount(1, $this->entriesOfKind($seller, $sessionIri, 'shortage'));
        $this->assertCount(1, $this->accountEntries($owner, $accountIri));
    }

    public function testSuccessBothCurrenciesAreSettledAtClose(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '40.00');
        $this->collect($seller, '700000.00', 'UZS');

        $this->closeSession($owner, $sessionIri, '40.00', '700000.00');
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(40.0, (float) $this->accountBalance($owner, $this->accountIri('cash', 'USD')));
        $this->assertSame(700000.0, (float) $this->accountBalance($owner, $this->accountIri('cash', 'UZS')));
    }

    public function testSuccessAccountJournalMatchesBalanceAfterClose(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $entry = $this->declareHandover($seller, $sessionIri, '20.00');
        $this->confirmHandover($owner, $entry['@id']);
        $this->closeSession($owner, $sessionIri, '30.00', '0');

        // Handed over mid-shift plus handed over at closing: two rows, one balance.
        $accountIri = $this->accountIri('cash', 'USD');
        $this->assertSame(50.0, (float) $this->accountBalance($owner, $accountIri));
        $this->assertCount(2, $this->accountEntries($owner, $accountIri));
        $this->assertAccountJournalMatchesBalance($owner, $accountIri);
    }
}
