<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Sale;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posting a sale is the central business transaction: in one go it writes outgoing stock
 * movements, realized profit entries and a positive debt entry, and it bumps the client's
 * running balance. Cancelling has to undo all four.
 */
class ChangeStatusApiTest extends BaseApiTestCase
{
    public function testSuccessPostSaleWritesAllLedgers(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $customer = 'Test Client 1';

        $productIri = $this->productIri('Test Product USD 1');
        $customerIri = $this->clientIri($customer);
        $stockBefore = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];

        $saleIri = $this->createDraftSale($client, $customer);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['status' => 'posted']);

        // 0. the posting moment is stamped on the document
        $this->assertNotNull($client->request(Request::METHOD_GET, $saleIri)->toArray()['postedAt']);

        // 1. stock went down by the sold quantity
        $stockAfter = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame($stockBefore - 20.0, $stockAfter);

        // 2. the sale total became debt, and the client's running balance moved with it
        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(120.0, (float) $sale['totalUsd']);
        $this->assertSame(120.0, (float) $sale['outstandingUsd']);

        $customerData = $client->request(Request::METHOD_GET, $customerIri)->toArray();
        $this->assertSame(120.0, (float) $customerData['debtUsd']);

        // 3. a positive debt entry exists for this sale
        $debts = $client->request(
            Request::METHOD_GET,
            '/api/debts?sale=' . basename($saleIri)
        )->toArray()['member'];
        $this->assertCount(1, $debts);
        $this->assertSame(120.0, (float) $debts[0]['amount']);
        $this->assertSame('USD', $debts[0]['currency']);

        // 4. profit was realized against the cheapest FIFO layer: 20 * (6.00 - 2.00)
        $profits = $client->request(Request::METHOD_GET, '/api/profits')->toArray()['member'];
        $saleProfit = 0.0;
        foreach ($profits as $profit) {
            if (($profit['sale']['@id'] ?? null) === $saleIri) {
                $saleProfit += (float) $profit['profit'];
            }
        }
        $this->assertSame(80.0, $saleProfit);
    }

    public function testSuccessCancelSaleReversesEveryLedger(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $customer = 'Test Client 1';

        $productIri = $this->productIri('Test Product USD 1');
        $customerIri = $this->clientIri($customer);
        $stockBefore = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $debtBefore = (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd'];

        $saleIri = $this->createDraftSale($client, $customer);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '20.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->changeStatus($client, $saleIri, 'cancelled');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['status' => 'cancelled']);

        // Stock and the client's balance are back where they started...
        $stockAfter = (float) $client->request(Request::METHOD_GET, $productIri)->toArray()['remainingQty'];
        $this->assertSame($stockBefore, $stockAfter);

        $debtAfter = (float) $client->request(Request::METHOD_GET, $customerIri)->toArray()['debtUsd'];
        $this->assertSame($debtBefore, $debtAfter);

        // ...and the sale itself owes nothing any more.
        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(0.0, (float) $sale['outstandingUsd']);

        // The ledgers are append-only: the reversal is a second entry that nets to zero.
        $debts = $client->request(Request::METHOD_GET, '/api/debts?sale=' . basename($saleIri))->toArray()['member'];
        $this->assertCount(2, $debts);
        $this->assertSame(0.0, array_sum(array_map(static fn (array $d): float => (float) $d['amount'], $debts)));
    }

    public function testIncorrectPostSaleWithoutItems(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $saleIri = $this->createDraftSale($client);

        $this->changeStatus($client, $saleIri, 'posted');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectMovePostedSaleBackToDraft(): void
    {
        $iri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $this->changeStatus($this->createSalesClientWithCredentials(), $iri, 'draft');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Posting twice must not write the ledgers twice. */
    public function testPostingAnAlreadyPostedSaleIsANoop(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '5.000', '6.00');

        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $debts = $client->request(Request::METHOD_GET, '/api/debts?sale=' . basename($saleIri))->toArray()['member'];
        $this->assertCount(1, $debts);
    }

    /**
     * Cancelling is final. Re-posting used to reach post() again, which reallocates the
     * items and so tries to delete allocations the profit entries still point at.
     */
    public function testIncorrectPostCancelledSale(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client);
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '5.000', '6.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->changeStatus($client, $saleIri, 'cancelled');

        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->changeStatus($client, $saleIri, 'draft');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame('cancelled', $client->request(Request::METHOD_GET, $saleIri)->toArray()['status']);

        // The ledger still holds exactly one entry and its reversal.
        $debts = $client->request(Request::METHOD_GET, '/api/debts?sale=' . basename($saleIri))->toArray()['member'];
        $this->assertCount(2, $debts);
    }

    public function testIncorrectChangeStatusAnonymously(): void
    {
        $iri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $this->changeStatus($this->createAnonymousClient(), $iri, 'cancelled');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
