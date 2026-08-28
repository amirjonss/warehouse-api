<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Taking money from a client. Only cash creates an obligation towards the company: card
 * and transfer go straight to the account, the seller never held them.
 */
class CollectApiTest extends CashTestCase
{
    public function testSuccessCashRaisesTheBalanceAndWritesAJournalRow(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);

        $paymentIri = $this->collect($client, '50.00');

        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);

        $entries = $this->cashEntries($client, $sessionIri);
        $this->assertCount(1, $entries);
        $this->assertSame('collect', $entries[0]['kind']);
        $this->assertSame('confirmed', $entries[0]['status']);
        $this->assertSame(50.0, (float) $entries[0]['amount'], 'a collect row goes into the journal positive');
        $this->assertSame('USD', $entries[0]['currency']);
        $this->assertSame($paymentIri, $this->iriOf($entries[0]['payment']));

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }

    public function testSuccessPaymentIsLinkedToTheSession(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);

        $paymentIri = $this->collect($client, '50.00');

        $this->assertSame(
            $sessionIri,
            $this->iriOf($client->request(Request::METHOD_GET, $paymentIri)->toArray()['cashSession'])
        );
    }

    /** Card money belongs to the company: visible in the turnover, absent from the balance. */
    public function testSuccessCardDoesNotTouchTheCashBalance(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);

        $this->collect($client, '50.00', 'USD', 'card');

        $this->assertSame(0.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
        $this->assertCount(0, $this->cashEntries($client, $sessionIri), 'a card payment must not produce journal rows');
    }

    public function testSuccessCardStillShowsUpInTheTurnover(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);

        $this->collect($client, '50.00', 'USD', 'card');

        $summary = $this->cashSummary($client, $sessionIri);
        $this->assertSame(0.0, (float) $summary['balanceUsd']);
        $this->assertCount(1, $summary['turnover']);
        $this->assertSame('card', $summary['turnover'][0]['method']);
        $this->assertSame('USD', $summary['turnover'][0]['currency']);
        $this->assertSame(50.0, (float) $summary['turnover'][0]['total']);
        $this->assertSame(1, $summary['turnover'][0]['count']);
    }

    public function testIncorrectAcceptCashWithoutAnOpenSession(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $saleIri = $this->sellOnCredit($client, '50.00');
        $paymentIri = $this->createDraftPayment($client, self::CUSTOMER, '50.00');
        $this->allocateTo($client, $paymentIri, $saleIri, '50.00');

        $this->changeStatus($client, $paymentIri, 'posted');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('нет открытой смены', $this->detail($client));
        // The payment stayed a draft: the client's debt is untouched.
        $this->assertSame('draft', $client->request(Request::METHOD_GET, $paymentIri)->toArray()['status']);
    }

    /** Non-cash is also taken by people who never handle cash, and they need no session. */
    public function testSuccessAcceptTransferWithoutAnOpenSession(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $paymentIri = $this->collect($client, '50.00', 'USD', 'transfer');

        $this->assertArrayNotHasKey(
            'cashSession',
            array_filter($client->request(Request::METHOD_GET, $paymentIri)->toArray(), static fn ($v) => $v !== null)
        );
    }

    public function testSuccessCancellingAPaymentReversesTheCash(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $paymentIri = $this->collect($client, '50.00');

        $this->changeStatus($client, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $client);

        $this->assertSame(0.0, (float) $this->session($client, $sessionIri)['balanceUsd']);

        // The row is not deleted, the opposite one is appended: the history stays evidence.
        $entries = $this->cashEntries($client, $sessionIri);
        $this->assertCount(2, $entries);
        $this->assertSame(-50.0, (float) $entries[1]['amount']);
        $this->assertStringContainsString('Сторно платежа', (string) $entries[1]['note']);

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }

    /** The money is already spent, so there is nothing to put back and cancelling is refused. */
    public function testIncorrectCancelPaymentAfterTheMoneyWasSpent(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $paymentIri = $this->collect($client, '50.00');
        $this->createExpense($client, '2026-08-25', '30.00', 'Fuel', 'USD');

        $this->changeStatus($client, $paymentIri, 'cancelled');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('уже потрачена или сдана', $this->detail($client));
        $this->assertSame(20.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
    }

    public function testSuccessSellerSeesOnlyOwnJournal(): void
    {
        $other = $this->createSecondSalesClientWithCredentials();
        $otherSession = $this->openCashSession($other);
        $this->collect($other, '50.00', 'USD', 'cash', 'Test Client 2');

        $seller = $this->createSalesClientWithCredentials();
        $this->openCashSession($seller);

        $listed = $seller->request(Request::METHOD_GET, '/api/cash_entries')->toArray()['member'];
        $this->assertCount(0, $listed, 'a seller must not see somebody else\'s journal rows');

        $adminSees = $this->createAdminClientWithCredentials()
            ->request(Request::METHOD_GET, '/api/cash_entries')
            ->toArray()['member'];
        $this->assertCount(1, $adminSees);
        $this->assertSame($otherSession, $this->iriOf($adminSees[0]['session']));
    }
}
