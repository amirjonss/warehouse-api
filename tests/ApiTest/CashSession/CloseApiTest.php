<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Response;

/**
 * Closing a session, done by the owner. Anything that was on the seller's account but
 * never arrived becomes a "shortage" row — that figure is why this module exists.
 */
class CloseApiTest extends CashTestCase
{
    public function testSuccessCloseWithTheExactAmount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $closed = $this->closeSession($admin, $sessionIri, '50.00', '0', 'Accepted in full');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->assertSame('closed', $closed['status']);
        $this->assertSame(0.0, (float) $closed['balanceUsd']);
        $this->assertNotNull($closed['closedAt']);
        $this->assertSame('admin', $closed['closedBy']['firstName']);

        $handovers = $this->entriesOfKind($admin, $sessionIri, 'handover');
        $this->assertCount(1, $handovers);
        $this->assertSame(-50.0, (float) $handovers[0]['amount']);
        // The owner entered the amount themselves: nothing left to confirm.
        $this->assertSame('confirmed', $handovers[0]['status']);
        $this->assertSame('Accepted in full', $handovers[0]['note']);

        $this->assertCount(0, $this->entriesOfKind($admin, $sessionIri, 'shortage'));
        $this->assertCashJournalMatchesBalance($admin, $sessionIri);
    }

    public function testSuccessShortageIsRecordedAsItsOwnRow(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $closed = $this->closeSession($admin, $sessionIri, '35.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->assertSame(0.0, (float) $closed['balanceUsd'], 'the balance is zeroed, but not silently');

        $shortages = $this->entriesOfKind($admin, $sessionIri, 'shortage');
        $this->assertCount(1, $shortages);
        $this->assertSame(-15.0, (float) $shortages[0]['amount']);
        $this->assertStringContainsString('Недостача при закрытии смены', (string) $shortages[0]['note']);

        $this->assertCashJournalMatchesBalance($admin, $sessionIri);
    }

    public function testSuccessCloseAnEmptySession(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);

        $admin = $this->createAdminClientWithCredentials();
        $closed = $this->closeSession($admin, $sessionIri);

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame('closed', $closed['status']);
        $this->assertCount(0, $this->cashEntries($admin, $sessionIri), 'an empty session produces no journal rows');
    }

    public function testIncorrectAcceptMoreThanIsOwed(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '80.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('принять', $this->detail($admin));
        $this->assertSame('open', $this->session($admin, $sessionIri)['status']);
    }

    public function testIncorrectAcceptNegativeAmount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '-10.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertSame('open', $this->session($admin, $sessionIri)['status']);
    }

    public function testIncorrectAcceptNonNumericAmount(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, 'пятьдесят');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('должна быть числом', $this->detail($admin));
        $this->assertSame('open', $this->session($admin, $sessionIri)['status']);
    }

    /** Until a handover is confirmed it is unclear who holds the money, so nothing can close. */
    public function testIncorrectCloseWithAnUnconfirmedHandover(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $this->declareHandover($seller, $sessionIri, '20.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '30.00');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('неподтверждённые сдачи', $this->detail($admin));
        $this->assertSame('open', $this->session($admin, $sessionIri)['status']);
    }

    public function testSuccessCloseAfterConfirmingTheHandover(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');
        $entry = $this->declareHandover($seller, $sessionIri, '20.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->confirmHandover($admin, $entry['@id']);
        $closed = $this->closeSession($admin, $sessionIri, '30.00');

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $this->assertSame('closed', $closed['status']);
        $this->assertSame(0.0, (float) $closed['balanceUsd']);
        $this->assertSame(0.0, (float) $closed['unconfirmedUsd']);
        $this->assertCashJournalMatchesBalance($admin, $sessionIri);
    }

    public function testIncorrectCloseTwice(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $admin = $this->createAdminClientWithCredentials();
        $this->closeSession($admin, $sessionIri, '50.00');
        $this->closeSession($admin, $sessionIri, '0');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
        $this->assertStringContainsString('уже закрыта', $this->detail($admin));
        // No second set of rows appeared in the journal.
        $this->assertCount(1, $this->entriesOfKind($admin, $sessionIri, 'handover'));
        $this->assertCashJournalMatchesBalance($admin, $sessionIri);
    }

    /** A seller cannot zero somebody's float: that is the owner's job. */
    public function testIncorrectSellerClosesOwnSession(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00');

        $this->closeSession($seller, $sessionIri, '50.00');

        $this->assertStatus(Response::HTTP_FORBIDDEN, $seller);
        $this->assertSame('open', $this->session($seller, $sessionIri)['status']);
    }

    /** USD and UZS settle independently: a shortage in one currency and none in the other. */
    public function testSuccessCloseSettlesEachCurrencySeparately(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sessionIri = $this->openCashSession($seller);
        $this->collect($seller, '50.00', 'USD');
        $this->collect($seller, '600000.00', 'UZS');

        $admin = $this->createAdminClientWithCredentials();
        $closed = $this->closeSession($admin, $sessionIri, '50.00', '550000.00');
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $this->assertSame(0.0, (float) $closed['balanceUsd']);
        $this->assertSame(0.0, (float) $closed['balanceUzs']);

        $shortages = $this->entriesOfKind($admin, $sessionIri, 'shortage');
        $this->assertCount(1, $shortages, 'dollars were handed over in full, the shortage is in sums only');
        $this->assertSame('UZS', $shortages[0]['currency']);
        $this->assertSame(-50000.0, (float) $shortages[0]['amount']);

        $this->assertCashJournalMatchesBalance($admin, $sessionIri);
    }
}
