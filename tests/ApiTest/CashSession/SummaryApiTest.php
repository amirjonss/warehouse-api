<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The session summary and the "cash on hand" tile. Card and transfer turnover is not
 * duplicated into the journal: it comes from a GROUP BY over the session's payments.
 */
class SummaryApiTest extends CashTestCase
{
    public function testSuccessSummaryShowsBalancesAndTurnoverByMethod(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00', 'USD', 'cash');
        // Card money is UZS: there is no dollar card, so USD is only ever cash.
        $this->collect($client, '30.00', 'UZS', 'card');
        $this->declareHandover($client, $sessionIri, '20.00');

        $summary = $this->cashSummary($client, $sessionIri);
        $this->assertStatus(Response::HTTP_CREATED, $client);

        $this->assertSame(30.0, (float) $summary['balanceUsd']);
        $this->assertSame(20.0, (float) $summary['unconfirmedUsd']);
        $this->assertSame(0.0, (float) $summary['balanceUzs']);

        $byMethod = [];
        foreach ($summary['turnover'] as $row) {
            $byMethod[$row['method']] = (float) $row['total'];
        }

        // The turnover shows everything the seller collected, including what never reached them.
        $this->assertSame(['card' => 30.0, 'cash' => 50.0], $byMethod);
    }

    public function testSuccessCancelledPaymentLeavesTheTurnover(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $paymentIri = $this->collect($client, '50.00', 'UZS', 'card');

        $this->assertCount(1, $this->cashSummary($client, $sessionIri)['turnover']);

        // The treasury is admin-only, so the account side is checked as the owner.
        $owner = $this->createAdminClientWithCredentials();
        $cardAccount = $this->accountIri('card', 'UZS');
        $this->assertSame(50.0, (float) $this->accountBalance($owner, $cardAccount));

        $this->changeStatus($client, $paymentIri, 'cancelled');
        $this->assertStatus(Response::HTTP_OK, $client);

        $this->assertCount(0, $this->cashSummary($client, $sessionIri)['turnover']);
        // Cancelling takes the money back off the card account, by a compensating row.
        $this->assertSame(0.0, (float) $this->accountBalance($owner, $cardAccount));
        $this->assertAccountJournalMatchesBalance($owner, $cardAccount);
    }

    public function testIncorrectReadSomebodyElsesSummary(): void
    {
        $owner = $this->createSecondSalesClientWithCredentials();
        $ownerSession = $this->openCashSession($owner);

        $intruder = $this->createSalesClientWithCredentials();
        $intruder->request(Request::METHOD_POST, $ownerSession . '/summary', ['body' => json_encode([])]);

        $this->assertStatus(Response::HTTP_NOT_FOUND, $intruder);
    }

    public function testSuccessAdminReadsAnybodysSummary(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $summary = $this->cashSummary($admin, $sessionIri);

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame(50.0, (float) $summary['balanceUsd']);
    }

    public function testSuccessOnHandsSumsEveryOpenSession(): void
    {
        $first = $this->createSalesClientWithCredentials();
        $firstSession = $this->openCashSession($first);
        $this->collect($first, '50.00');

        $second = $this->createSecondSalesClientWithCredentials();
        $secondSession = $this->openCashSession($second);
        $this->collect($second, '40.00', 'USD', 'cash', 'Test Client 2');
        $this->declareHandover($second, $secondSession, '15.00');

        $admin = $this->createAdminClientWithCredentials();
        $onHands = $admin->request(Request::METHOD_POST, '/api/cash_sessions/on_hands', [
            'body' => json_encode([]),
        ])->toArray();

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame(75.0, (float) $onHands['balanceUsd'], '50 on the first seller plus 25 on the second');
        $this->assertSame(15.0, (float) $onHands['unconfirmedUsd']);
        $this->assertSame(2, $onHands['openSessions']);

        // A closed session drops out of the tile: its money is already with the owner.
        $this->closeSession($admin, $firstSession, '50.00');
        $after = $admin->request(Request::METHOD_POST, '/api/cash_sessions/on_hands', [
            'body' => json_encode([]),
        ])->toArray();

        $this->assertSame(25.0, (float) $after['balanceUsd']);
        $this->assertSame(1, $after['openSessions']);
    }

    /** How much cash sellers hold is the owner's figure, not the seller's. */
    public function testIncorrectSellerReadsOnHands(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $seller->request(Request::METHOD_POST, '/api/cash_sessions/on_hands', ['body' => json_encode([])]);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $seller);
    }
}
