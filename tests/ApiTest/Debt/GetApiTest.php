<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Debt;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** The debt ledger is append-only: every posting adds a row, nothing is ever updated. */
class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetDebtCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/debts');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // A single positive entry from the posted fixture sale.
        $this->assertSame(1, $response->toArray()['totalItems']);
    }

    public function testSuccessFilterDebtsBySale(): void
    {
        $saleIri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/debts?sale=' . basename($saleIri)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $debts = $response->toArray()['member'];
        $this->assertCount(1, $debts);
        $this->assertSame(50.0, (float) $debts[0]['amount']);
        $this->assertSame('USD', $debts[0]['currency']);
        $this->assertSame('sale', $debts[0]['docType']);
    }

    /** The client's denormalized balance must always equal the sum of their ledger rows. */
    public function testClientBalanceMatchesTheLedger(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $customerIri = $this->clientIri('Test Client 2');

        $debts = $client->request(Request::METHOD_GET, '/api/debts')->toArray()['member'];
        $sum = 0.0;
        foreach ($debts as $debt) {
            if (basename((string) $debt['client']) === basename($customerIri) && $debt['currency'] === 'USD') {
                $sum += (float) $debt['amount'];
            }
        }

        $customer = $client->request(Request::METHOD_GET, $customerIri)->toArray();
        $this->assertSame((float) $customer['debtUsd'], $sum);
    }

    public function testIncorrectGetDebtCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/debts');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
