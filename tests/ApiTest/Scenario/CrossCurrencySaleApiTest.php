<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Scenario;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cost lives on the batch in the currency it was bought in. Selling in a different
 * currency therefore needs an explicit rate on the line, otherwise the margin cannot
 * be worked out. Missing it has to be a 422, not a crash.
 */
class CrossCurrencySaleApiTest extends BaseApiTestCase
{
    public function testSellingUsdStockInUzsWithoutARateIsRefused(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1');

        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '75000.00', 'UZS', null);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSellingUsdStockInUzsWithARateSucceeds(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1');

        $item = $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '75000.00', 'UZS', '12500');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('750000.00', $item['total']);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(750000.0, (float) $sale['totalUzs']);
        $this->assertSame(0.0, (float) $sale['totalUsd']);
    }

    public function testCrossCurrencySaleBooksDebtInTheSaleCurrency(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $customerIri = $this->clientIri('Test Client 1');

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '75000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // The debt is denominated in UZS, the currency the goods were sold in.
        $customer = $client->request(Request::METHOD_GET, $customerIri)->toArray();
        $this->assertSame(750000.0, (float) $customer['debtUzs']);
        $this->assertSame(0.0, (float) $customer['debtUsd']);

        $sale = $client->request(Request::METHOD_GET, $saleIri)->toArray();
        $this->assertSame(750000.0, (float) $sale['outstandingUzs']);
    }

    /** Profit converts the UZS revenue back into the batch's USD cost currency. */
    public function testCrossCurrencyProfitUsesTheLineRate(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 1', '10.000', '75000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // 10 * (75000 / 12500) = 60.00 USD revenue, against 10 * 2.00 = 20.00 USD cost.
        $profits = $client->request(Request::METHOD_GET, '/api/profits')->toArray()['member'];
        $saleProfit = 0.0;
        foreach ($profits as $profit) {
            if (($profit['sale']['@id'] ?? null) === $saleIri) {
                $saleProfit += (float) $profit['profit'];
                $this->assertSame('USD', $profit['currency']);
            }
        }

        $this->assertSame(40.0, $saleProfit);
    }

    /** Selling UZS-costed stock in UZS needs no rate at all. */
    public function testSameCurrencySaleNeedsNoRate(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1');

        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '10.000', '60000.00', 'UZS', null);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
