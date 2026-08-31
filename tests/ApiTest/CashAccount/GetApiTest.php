<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashAccount;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The account set is closed and seeded: four rows, no create endpoint. USD appears once,
 * as cash, because the business has neither a dollar card nor a currency bank account.
 */
class GetApiTest extends BaseApiTestCase
{
    public function testSuccessAdminListsTheFourAccounts(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $accounts = $client->request(Request::METHOD_GET, '/api/cash_accounts')->toArray()['member'];
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertCount(4, $accounts);

        $pairs = [];
        foreach ($accounts as $account) {
            $pairs[] = $account['kind'] . ':' . $account['currency'];
        }
        sort($pairs);

        $this->assertSame(['bank:UZS', 'card:UZS', 'cash:USD', 'cash:UZS'], $pairs);
    }

    public function testSuccessAccountExposesKindCurrencyNameAndBalance(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $account = $client->request(Request::METHOD_GET, $this->accountIri('cash', 'UZS'))->toArray();

        $this->assertSame('cash', $account['kind']);
        $this->assertSame('UZS', $account['currency']);
        $this->assertSame('Наличные UZS', $account['name']);
        $this->assertSame(0.0, (float) $account['balance']);
        $this->assertTrue($account['isActive']);
    }

    public function testIncorrectListAccountsAsSales(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/cash_accounts');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectListAccountsAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/cash_accounts');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
