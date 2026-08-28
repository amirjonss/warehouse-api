<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opening a float. One open session per seller is a database-level guarantee, a partial
 * unique index, rather than a check in the code.
 */
class OpenApiTest extends CashTestCase
{
    public function testSuccessOpenSessionForSelf(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $sessionIri = $this->openCashSession($client);
        $session = $this->session($client, $sessionIri);

        $this->assertSame('open', $session['status']);
        $this->assertSame(0.0, (float) $session['balanceUsd']);
        $this->assertSame(0.0, (float) $session['balanceUzs']);
        $this->assertSame(0.0, (float) $session['unconfirmedUsd']);
        $this->assertSame(0.0, (float) $session['unconfirmedUzs']);
        $this->assertNull($session['closedAt']);
        // Opened for themselves: the money's owner and the opener are the same person.
        $this->assertSame('sales', $session['user']['firstName']);
        $this->assertSame('sales', $session['openedBy']['firstName']);
        $this->assertMatchesRegularExpression('/^CS-\d{5}$/', $session['number']);
    }

    public function testIncorrectOpenSecondSessionForTheSameUser(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $this->openCashSession($client);

        $client->request(Request::METHOD_POST, '/api/cash_sessions', ['body' => json_encode([])]);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $client);
        $this->assertStringContainsString('уже открыта смена', $this->detail($client));
    }

    public function testSuccessTwoSellersHaveTheirOwnSessions(): void
    {
        $first = $this->createSalesClientWithCredentials();
        $firstIri = $this->openCashSession($first);

        $second = $this->createSecondSalesClientWithCredentials();
        $secondIri = $this->openCashSession($second);

        $this->assertNotSame($firstIri, $secondIri);
        $this->assertSame('sales2', $this->session($second, $secondIri)['user']['firstName']);
    }

    public function testIncorrectSellerOpensSessionForSomebodyElse(): void
    {
        $victim = $this->createSecondSalesClientWithCredentials();
        $victimIri = $this->openCashSession($victim);
        $victimUserIri = $this->session($victim, $victimIri)['user']['@id'];

        // We are not closing anybody's session here, just trying to open one in their name.
        $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/cash_sessions', [
            'body' => json_encode(['user' => $victimUserIri]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSuccessAdminOpensSessionForSeller(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $sellerSessionIri = $this->openCashSession($seller);
        $sellerUserIri = $this->session($seller, $sellerSessionIri)['user']['@id'];
        $admin = $this->createAdminClientWithCredentials();

        // The seller's own session is already open, so the owner closes it first.
        $this->closeSession($admin, $sellerSessionIri);
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $response = $admin->request(Request::METHOD_POST, '/api/cash_sessions', [
            'body' => json_encode(['user' => $sellerUserIri]),
        ]);

        $this->assertStatus(Response::HTTP_CREATED, $admin);
        $opened = $response->toArray();
        $this->assertSame('sales', $opened['user']['firstName']);
        // The money is on the seller's account, while the administrator opened the session.
        $this->assertSame('admin', $opened['openedBy']['firstName']);
    }

    public function testSuccessReopenAfterClose(): void
    {
        $seller = $this->createSalesClientWithCredentials();
        $admin = $this->createAdminClientWithCredentials();

        $firstIri = $this->openCashSession($seller);
        $this->closeSession($admin, $firstIri);
        $this->assertStatus(Response::HTTP_CREATED, $admin);

        $secondIri = $this->openCashSession($seller);

        $this->assertNotSame($firstIri, $secondIri);
        $this->assertSame('closed', $this->session($seller, $firstIri)['status']);
        $this->assertSame(0.0, (float) $this->session($seller, $secondIri)['balanceUsd']);
    }

    public function testIncorrectOpenSessionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/cash_sessions', [
            'body' => json_encode([]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /** Somebody else's cash is somebody else's money: the list shows only the seller's own float. */
    public function testSuccessSellerSeesOnlyOwnSessions(): void
    {
        $other = $this->createSecondSalesClientWithCredentials();
        $this->openCashSession($other);

        $seller = $this->createSalesClientWithCredentials();
        $ownIri = $this->openCashSession($seller);

        $listed = $seller->request(Request::METHOD_GET, '/api/cash_sessions')->toArray()['member'];

        $this->assertCount(1, $listed);
        $this->assertSame($ownIri, $listed[0]['@id']);
    }

    public function testSuccessAdminSeesEverySession(): void
    {
        $this->openCashSession($this->createSalesClientWithCredentials());
        $this->openCashSession($this->createSecondSalesClientWithCredentials());

        $listed = $this->createAdminClientWithCredentials()
            ->request(Request::METHOD_GET, '/api/cash_sessions')
            ->toArray()['member'];

        $this->assertCount(2, $listed);
    }

}
