<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handing cash to the owner, in two steps. The money leaves the balance immediately,
 * because physically the seller no longer has it, but accountability is only lifted by
 * confirmation. That gap is what makes the record evidence rather than one side's word.
 */
class HandoverApiTest extends CashTestCase
{
    public function testSuccessDeclareMovesMoneyOutOfBalanceIntoUnconfirmed(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $entry = $this->declareHandover($client, $sessionIri, '20.00', 'USD', 'Handed over at the warehouse');
        $this->assertStatus(Response::HTTP_CREATED, $client);

        $this->assertSame('handover', $entry['kind']);
        $this->assertSame('declared', $entry['status']);
        $this->assertSame(-20.0, (float) $entry['amount'], 'a handover row goes into the journal negative');
        $this->assertSame('Handed over at the warehouse', $entry['note']);
        $this->assertNull($entry['confirmedAt']);

        $session = $this->session($client, $sessionIri);
        $this->assertSame(30.0, (float) $session['balanceUsd'], 'the money left the bag straight away');
        $this->assertSame(20.0, (float) $session['unconfirmedUsd'], 'but the debt towards the company remains');

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }

    public function testSuccessConfirmClearsTheUnconfirmedAmountOnly(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $entry = $this->declareHandover($seller, $sessionIri, '20.00');

        $admin = $this->createAdminClientWithCredentials();
        $confirmed = $this->confirmHandover($admin, $entry['@id']);
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->assertSame('confirmed', $confirmed['status']);
        $this->assertSame('admin', $confirmed['confirmedBy']['firstName']);
        $this->assertNotNull($confirmed['confirmedAt']);

        $session = $this->session($seller, $sessionIri);
        $this->assertSame(30.0, (float) $session['balanceUsd'], 'confirmation does not touch the balance');
        $this->assertSame(0.0, (float) $session['unconfirmedUsd']);

        $this->assertCashJournalMatchesBalance($seller, $sessionIri);
    }

    public function testIncorrectDeclareMoreThanTheBalance(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $this->declareHandover($client, $sessionIri, '80.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('сдать', $this->detail($client));
        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
    }

    public function testIncorrectDeclareZero(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $this->declareHandover($client, $sessionIri, '0');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('больше нуля', $this->detail($client));
    }

    public function testIncorrectDeclareNegative(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $this->declareHandover($client, $sessionIri, '-10.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('больше нуля', $this->detail($client));
    }

    /**
     * On a non-numeric string bcmath throws a ValueError, which surfaces as a 500. The
     * amount's format has to be rejected by validation before the service runs.
     */
    public function testIncorrectDeclareNonNumericAmount(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $this->declareHandover($client, $sessionIri, 'one hundred thousand');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('должна быть числом', $this->detail($client));
        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
    }

    public function testIncorrectDeclareScientificNotation(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        // is_numeric('1e3') is true, yet bcmath does not understand that notation.
        $this->declareHandover($client, $sessionIri, '1e3');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
    }

    public function testIncorrectConfirmTwice(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $entry = $this->declareHandover($seller, $sessionIri, '20.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->confirmHandover($admin, $entry['@id']);
        $this->confirmHandover($admin, $entry['@id']);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('уже подтверждена', $this->detail($admin));
        $this->assertSame(0.0, (float) $this->session($seller, $sessionIri)['unconfirmedUsd']);
    }

    public function testIncorrectConfirmACollectRow(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $collectRow = $this->entriesOfKind($seller, $sessionIri, 'collect')[0];

        $admin = $this->createAdminClientWithCredentials();
        $this->confirmHandover($admin, $collectRow['@id']);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('только сдачу денег', $this->detail($admin));
    }

    /** The owner confirms receipt, or the record goes back to being one side's word. */
    public function testIncorrectSellerConfirmsOwnHandover(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $entry = $this->declareHandover($seller, $sessionIri, '20.00');

        $this->confirmHandover($seller, $entry['@id']);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $seller);
        $this->assertSame(20.0, (float) $this->session($seller, $sessionIri)['unconfirmedUsd']);
    }

    public function testIncorrectDeclareFromSomebodyElsesSession(): void
    {
        $owner = $this->createSecondSalesClientWithCredentials();
        $ownerSession = $this->openCashSession($owner);
        $this->collect($owner, '50.00', 'USD', 'cash', 'Test Client 2');

        $intruder = $this->createSalesClientWithCredentials();
        $this->openCashSession($intruder);
        $this->declareHandover($intruder, $ownerSession, '20.00');

        // Another seller's session is invisible to them, so the read answers with a 404.
        $this->assertStatus(Response::HTTP_NOT_FOUND, $intruder);
        $this->assertSame(50.0, (float) $this->session($owner, $ownerSession)['balanceUsd']);
    }

    public function testIncorrectDeclareIntoAClosedSession(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '50.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->declareHandover($seller, $sessionIri, '10.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $seller);
    }

    /** Handing over both dollars and sums means two documents, not one with a conversion. */
    public function testSuccessHandoversAreTrackedPerCurrency(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00', 'USD');
        $this->collect($client, '600000.00', 'UZS');

        $this->declareHandover($client, $sessionIri, '20.00', 'USD');
        $this->declareHandover($client, $sessionIri, '500000.00', 'UZS');

        $session = $this->session($client, $sessionIri);
        $this->assertSame(30.0, (float) $session['balanceUsd']);
        $this->assertSame(100000.0, (float) $session['balanceUzs']);
        $this->assertSame(20.0, (float) $session['unconfirmedUsd']);
        $this->assertSame(500000.0, (float) $session['unconfirmedUzs']);

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }
}
