<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An expense paid out of the float. There are no advances: only what has been collected
 * can be spent, and only in the same currency — dollars and sums are not interchangeable.
 */
class ExpenseApiTest extends CashTestCase
{
    public function testSuccessExpenseReducesTheBalance(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $expenseIri = $this->createExpense($client, '2026-08-25', '30.00', 'Fuel', 'USD');

        $this->assertSame(20.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
        $this->assertSame(
            $sessionIri,
            $this->iriOf($client->request(Request::METHOD_GET, $expenseIri)->toArray()['cashSession'])
        );

        $entries = $this->entriesOfKind($client, $sessionIri, 'expense');
        $this->assertCount(1, $entries);
        $this->assertSame(-30.0, (float) $entries[0]['amount'], 'an expense row goes into the journal negative');
        $this->assertStringContainsString('Fuel', (string) $entries[0]['note']);

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }

    public function testIncorrectExpenseOverTheBalance(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');

        $client->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Too much',
                'amount' => '80.00',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('провести нельзя', $this->detail($client));
        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);
    }

    /** Plenty of sums, no dollars: "there is enough in total" is not an argument here. */
    public function testIncorrectExpenseInACurrencyWithoutBalance(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '600000.00', 'UZS');

        $client->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Expense in dollars',
                'amount' => '10.00',
                'currency' => 'USD',
            ]),
        ]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertSame(600000.0, (float) $this->session($client, $sessionIri)['balanceUzs']);
    }

    /**
     * Before the treasury existed a sessionless expense was simply recorded and no money
     * left anything. Now there is somewhere for it to leave from, so an expense with no
     * source would overstate the company's cash for good — it is refused instead.
     */
    public function testIncorrectSellerExpenseWithoutASession(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $this->createExpenseRaw($client, '30.00', 'USD');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('нет открытой смены', $this->detail($client));
        $this->assertCount(0, $client->request(Request::METHOD_GET, '/api/expenses')->toArray()['member']);
    }

    /**
     * The owner spends out of the treasury, which is a different pot: no seller's float
     * moves, and the money leaves the account instead.
     */
    public function testSuccessOwnerExpenseFromAnAccountLeavesFloatsAlone(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $owner = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');
        $this->fund($owner, $accountIri, '1000000.00');

        $this->createExpense($owner, '2026-08-25', '250000.00', 'Аренда', 'UZS', $accountIri);
        $this->assertStatus(Response::HTTP_CREATED, $owner);

        $this->assertSame(750000.0, (float) $this->accountBalance($owner, $accountIri));
        $this->assertSame(50.0, (float) $this->session($seller, $sessionIri)['balanceUsd']);
        $this->assertCount(1, $this->cashEntries($seller, $sessionIri));
        $this->assertAccountJournalMatchesBalance($owner, $accountIri);
    }

    /** Raw POST: the helper asserts a 201, and here the point is that there is none. */
    private function createExpenseRaw(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $amount, string $currency): void
    {
        $client->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => $amount,
                'currency' => $currency,
            ]),
        ]);
    }

    public function testSuccessDeletingAnExpenseReturnsTheMoney(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($client);
        $this->collect($client, '50.00');
        $expenseIri = $this->createExpense($client, '2026-08-25', '30.00', 'Fuel', 'USD');

        $client->request(Request::METHOD_DELETE, $expenseIri);
        $this->assertStatus(Response::HTTP_NO_CONTENT, $client);

        $this->assertSame(50.0, (float) $this->session($client, $sessionIri)['balanceUsd']);

        // The journal is not rewritten: both rows survive, and both read fine without the expense.
        $entries = $this->entriesOfKind($client, $sessionIri, 'expense');
        $this->assertCount(2, $entries);
        $this->assertSame(-30.0, (float) $entries[0]['amount']);
        $this->assertSame(30.0, (float) $entries[1]['amount']);
        $this->assertNull($this->iriOf($entries[0]['expense']), 'the link to the deleted expense must be nulled out');
        $this->assertStringContainsString('Fuel', (string) $entries[0]['note']);
        $this->assertStringContainsString('Сторно расхода', (string) $entries[1]['note']);

        $this->assertCashJournalMatchesBalance($client, $sessionIri);
    }

    /**
     * Deleting somebody else's expense would return money to their session and grow their
     * float. Only the owner is allowed to do that.
     */
    public function testIncorrectDeleteSomebodyElsesExpense(): void
    {
        $owner = $this->createSecondSalesClientWithCredentials();
        $ownerSession = $this->openCashSession($owner);
        $this->collect($owner, '50.00', 'USD', 'cash', 'Test Client 2');
        $expenseIri = $this->createExpense($owner, '2026-08-25', '30.00', 'Somebody else\'s fuel', 'USD');

        $intruder = $this->createSalesClientWithCredentials();
        $intruder->request(Request::METHOD_DELETE, $expenseIri);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $intruder);
        $this->assertSame(20.0, (float) $this->session($owner, $ownerSession)['balanceUsd'], 'the other seller\'s balance did not move');
    }

    public function testSuccessAdminDeletesSomebodyElsesExpense(): void
    {
        $owner = $this->createSalesClientWithCredentials();
        $ownerSession = $this->openCashSession($owner);
        $this->collect($owner, '50.00');
        $expenseIri = $this->createExpense($owner, '2026-08-25', '30.00', 'Fuel', 'USD');

        $admin = $this->createAdminClientWithCredentials();
        $admin->request(Request::METHOD_DELETE, $expenseIri);

        $this->assertStatus(Response::HTTP_NO_CONTENT, $admin);
        $this->assertSame(50.0, (float) $this->session($owner, $ownerSession)['balanceUsd']);
    }

    /** The session is closed: the sum is already part of the settlement and must not move. */
    public function testIncorrectDeleteExpenseOfAClosedSession(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $expenseIri = $this->createExpense($seller, '2026-08-25', '30.00', 'Fuel', 'USD');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '20.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $seller->request(Request::METHOD_DELETE, $expenseIri);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $seller);
        $this->assertStringContainsString('закрытой смене', $this->detail($seller));
    }
}
