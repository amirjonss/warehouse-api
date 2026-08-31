<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Wallet;

use Symfony\Component\HttpFoundation\Response;

/**
 * Handing money over is two events, not one: the seller's bag empties when they declare
 * it, but the company only owns the money once the owner acknowledges it. The treasury
 * therefore moves on confirmation, not on declaration.
 */
class HandoverInflowApiTest extends WalletTestCase
{
    public function testSuccessConfirmedHandoverCreditsTheCashAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'USD');

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $entry = $this->declareHandover($seller, $sessionIri, '30.00');
        $this->confirmHandover($owner, $entry['@id']);
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(30.0, (float) $this->accountBalance($owner, $accountIri));

        $entries = $this->accountEntries($owner, $accountIri);
        $this->assertCount(1, $entries);
        $this->assertSame('handover', $entries[0]['kind']);
        $this->assertStringContainsString('Сдача из смены', (string) $entries[0]['note']);
    }

    public function testSuccessDeclaredHandoverDoesNotCreditTheAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'USD');

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $this->declareHandover($seller, $sessionIri, '30.00');

        // The money left the seller's bag but is still in transit.
        $this->assertSame(20.0, (float) $this->session($seller, $sessionIri)['balanceUsd']);
        $this->assertSame(30.0, (float) $this->session($seller, $sessionIri)['unconfirmedUsd']);
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $accountIri));
    }

    public function testSuccessUzsHandoverGoesToTheUzsCashAccount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '600000.00', 'UZS');

        $entry = $this->declareHandover($seller, $sessionIri, '600000.00', 'UZS');
        $this->confirmHandover($owner, $entry['@id']);

        $this->assertSame(600000.0, (float) $this->accountBalance($owner, $this->accountIri('cash', 'UZS')));
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $this->accountIri('cash', 'USD')));
    }

    public function testSuccessAccountJournalMatchesBalanceAfterConfirm(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $owner = $this->createAdminClientWithCredentials();

        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        foreach (['10.00', '15.00'] as $amount) {
            $entry = $this->declareHandover($seller, $sessionIri, $amount);
            $this->confirmHandover($owner, $entry['@id']);
        }

        $this->assertSame(25.0, (float) $this->accountBalance($owner, $this->accountIri('cash', 'USD')));
        $this->assertAccountJournalMatchesBalance($owner, $this->accountIri('cash', 'USD'));
        $this->assertCashJournalMatchesBalance($seller, $sessionIri);
    }
}
