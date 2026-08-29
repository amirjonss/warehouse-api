<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Sale;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales analysis. Arguments come from the query string: this operation has input: false.
 *
 * The fixtures already hold one posted sale on 2026-08-10 — 10 x 5.00 USD of
 * "Test Product USD 1", bought at 2.00, so 50.00 of revenue against 20.00 of cost. Each
 * test adds a second sale in a different month to prove the periods are split apart.
 *
 * The currency is mandatory: every column here is money, and dollars and sums are never
 * summed together.
 */
class AnalysisApiTest extends BaseApiTestCase
{
    public function testSuccessSplitsTheMonthsAndComputesMargin(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=month&currency=USD');

        $this->assertSame('month', $data['interval']);
        $this->assertSame('USD', $data['baseCurrency']);
        $this->assertCount(2, $data['rows'], 'август из фикстур и добавленный сентябрь');

        [$august, $september] = $data['rows'];

        $this->assertSame('2026-08-01', $august['period']);
        $this->assertSame(1, $august['documents']);
        $this->assertSame(10.0, (float) $august['quantity']);
        $this->assertSame(50.0, (float) $august['revenue']);
        $this->assertSame(20.0, (float) $august['cost'], 'FIFO взял партию по 2.00');
        $this->assertSame(30.0, (float) $august['profit']);
        $this->assertSame('60.00', $august['margin']);

        $this->assertSame('2026-09-01', $september['period']);
        $this->assertSame(200.0, (float) $september['revenue'], '20 x 10.00');
        $this->assertSame(80.0, (float) $september['cost'], 'закуп по 4.00');
        $this->assertSame(120.0, (float) $september['profit']);
        $this->assertSame('60.00', $september['margin']);
    }

    /** Cost is never reported on its own: revenue minus profit must always close. */
    public function testSuccessCostAndProfitAddUpToRevenue(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=month&currency=USD');

        foreach ($data['rows'] as $row) {
            $this->assertSame(
                (float) $row['revenue'],
                (float) $row['cost'] + (float) $row['profit'],
                'строка ' . $row['period']
            );
        }
    }

    public function testSuccessGrandTotalSumsEveryRow(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=month&currency=USD');
        $total = $data['total'];

        $this->assertNull($total['period'], 'итог не принадлежит ни одному периоду');
        $this->assertSame(2, $total['documents']);
        $this->assertSame(30.0, (float) $total['quantity']);
        $this->assertSame(250.0, (float) $total['revenue']);
        $this->assertSame(100.0, (float) $total['cost']);
        $this->assertSame(150.0, (float) $total['profit']);
        $this->assertSame('60.00', $total['margin']);
    }

    public function testSuccessDayIntervalSplitsFiner(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=day&currency=USD');

        $this->assertSame('day', $data['interval']);
        $this->assertSame(['2026-08-10', '2026-09-10'], array_column($data['rows'], 'period'));
    }

    public function testSuccessWeekIntervalGroupsByWeek(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=week&currency=USD');

        // Postgres weeks start on Monday, so each sale lands on the Monday of its week.
        $this->assertSame(['2026-08-10', '2026-09-07'], array_column($data['rows'], 'period'));
    }

    public function testSuccessPeriodNarrowsTheRows(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInSeptember($client);

        $data = $this->analyse($client, 'interval=month&currency=USD&from=2026-09-01&to=2026-10-01');

        $this->assertCount(1, $data['rows']);
        $this->assertSame('2026-09-01', $data['rows'][0]['period']);
        $this->assertSame(200.0, (float) $data['total']['revenue']);
    }

    public function testSuccessPinnedCurrencySkipsConversion(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInUzs($client);

        $data = $this->analyse($client, 'interval=month&currency=UZS');

        $this->assertSame('UZS', $data['baseCurrency']);
        $this->assertCount(1, $data['rows'], 'долларовая продажа из фикстур отфильтрована');
        $this->assertSame(300000.0, (float) $data['rows'][0]['revenue'], '5 x 60 000, сумы остались сумами');
    }

    /**
     * The two currencies are two separate reports over the same sales, never one merged
     * number. A sale carrying lines in both is a document in each of them, which is why the
     * two document counts must not be added together.
     */
    public function testSuccessEachCurrencyIsItsOwnReport(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellBothCurrenciesInOneSale($client);

        $usd = $this->analyse($client, 'interval=month&currency=USD');
        $uzs = $this->analyse($client, 'interval=month&currency=UZS');

        $this->assertSame(250.0, (float) $usd['total']['revenue'], '50.00 из фикстур + 200.00');
        $this->assertSame(300000.0, (float) $uzs['total']['revenue'], 'сумы остались сумами');

        $september = $usd['rows'][1];
        $this->assertSame(1, $september['documents']);
        $this->assertSame(1, $uzs['rows'][0]['documents'], 'та же продажа сосчитана в обоих отчётах');
    }

    public function testSuccessCategoryNarrowsTheSelection(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellInUzs($client);
        $categoryId = basename($this->findIriBy(Category::class, ['name' => 'Test Category 2']));

        $data = $this->analyse($client, 'interval=month&currency=UZS&category=' . $categoryId);

        $this->assertCount(1, $data['rows'], 'вторая категория — только сумовой товар');
        $this->assertSame(300000.0, (float) $data['total']['revenue']);
    }

    public function testSuccessEmptyPeriodIsZeroed(): void
    {
        $client = $this->createAdminClientWithCredentials();

        $data = $this->analyse($client, 'currency=USD&from=2000-01-01&to=2000-12-31');

        $this->assertSame([], $data['rows']);
        $this->assertSame(0, $data['total']['documents']);
        $this->assertSame('0.00', $data['total']['margin'], 'без деления на ноль');
    }

    /**
     * A dollar batch sold for sums. The profit journal stores such a row in the *cost*
     * currency — dollars — while the revenue is in sums, so it has to be converted before
     * the two meet. Getting this wrong once produced a margin of 10 057 %.
     *
     * 10 x 60 000 UZS against a cost of 4.00 USD at a rate of 12 500:
     * profit in USD is 600 000 / 12 500 - 40.00 = 8.00, which is 100 000 UZS.
     */
    public function testSuccessDollarBatchSoldForSums(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '10.000', '60000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');

        $data = $this->analyse($client, 'interval=month&currency=UZS');
        $total = $data['total'];

        $this->assertSame(600000.0, (float) $total['revenue']);
        $this->assertSame(100000.0, (float) $total['profit'], 'прибыль приведена к валюте продажи');
        $this->assertSame(500000.0, (float) $total['cost']);
        $this->assertSame('16.66', $total['margin']);
    }

    /** The mirror case: a sums batch sold for dollars, on top of the dollar fixture sale. */
    public function testSuccessSumsBatchSoldForDollars(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '10.000', '5.00', 'USD', '12500');
        $this->changeStatus($client, $saleIri, 'posted');

        $data = $this->analyse($client, 'interval=month&currency=USD');
        $september = $data['rows'][1];

        // 10 x 5.00 x 12 500 - 10 x 40 000 = 225 000 UZS of profit, i.e. 18.00 USD.
        $this->assertSame(50.0, (float) $september['revenue']);
        $this->assertSame(18.0, (float) $september['profit']);
        $this->assertSame(32.0, (float) $september['cost']);
    }

    /** A draft has moved no goods and must not show up as turnover. */
    public function testSuccessDraftSalesAreIgnored(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $draftIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $draftIri, 'Test Product USD 2', '20.000', '10.00');

        $data = $this->analyse($client, 'interval=month&currency=USD');

        $this->assertCount(1, $data['rows'], 'только проведённая продажа из фикстур');
        $this->assertSame(50.0, (float) $data['total']['revenue']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidCriteria(): array
    {
        return [
            'unknown interval' => ['interval=quarter', 'интервал'],
            'missing currency' => ['interval=month', 'Укажите валюту'],
            'unknown currency' => ['currency=EUR', 'валюта'],
            'wrong date format' => ['currency=USD&from=2025/09/01', 'ГГГГ-ММ-ДД'],
            'non numeric category' => ['currency=USD&category=x', 'идентификатором'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCriteria')]
    public function testIncorrectCriteriaAreRefused(string $query, string $expectedInMessage): void
    {
        $client = $this->createAdminClientWithCredentials();

        $client->request(Request::METHOD_POST, '/api/sales/analysis?' . $query);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStringContainsString(
            $expectedInMessage,
            (string) (json_decode($client->getResponse()->getContent(false), true)['detail'] ?? '')
        );
    }

    /** Cost and margin are the owner's numbers. */
    public function testIncorrectForASeller(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/sales/analysis');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/sales/analysis');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyse(Client $client, string $query): array
    {
        $response = $client->request(Request::METHOD_POST, '/api/sales/analysis?' . $query);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray();
    }

    /** 20 x 10.00 USD bought at 4.00 — a second month with the same 60 % margin. */
    private function sellInSeptember(Client $client): void
    {
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '20.000', '10.00');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent(false));
    }

    /** 5 x 60 000 UZS — a sale that only the UZS report sees. */
    private function sellInUzs(Client $client): void
    {
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '5.000', '60000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent(false));
    }

    /** One document with lines in both currencies — it shows up in both reports. */
    private function sellBothCurrenciesInOneSale(Client $client): void
    {
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '20.000', '10.00');
        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '5.000', '60000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent(false));
    }
}
