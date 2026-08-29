<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ABC analysis. Arguments come from the query string: this operation has input: false.
 *
 * The three products are deliberately built so that revenue and quantity rank them in a
 * different order, and so that one of them is sold below cost — that is what exercises the
 * rule about non-positive values.
 *
 * On top of the fixture sale (10 x 5.00 USD of "Test Product USD 1" = 50.00) each test adds:
 *   "Test Product USD 2" 15 x 10.00 USD  = 150.00 USD
 *   "Test Product UZS 1" 40 x 6 000 UZS  = 240 000 UZS
 *
 * Money metrics never mix the two: a currency has to be named, and only lines in it count.
 */
class AbcAnalysisApiTest extends BaseApiTestCase
{
    private const RATE = '12500';

    public function testSuccessRevenueRanksAndClassifies(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=revenue&currency=USD');

        $this->assertSame('revenue', $data['metric']);
        $this->assertSame('USD', $data['baseCurrency']);
        $this->assertSame(200.0, (float) $data['total'], '150.00 + 50.00, сумовая позиция сюда не входит');

        $items = $data['items'];
        $this->assertCount(2, $items);

        $this->assertSame(['Test Product USD 2', 'Test Product USD 1'], array_column($items, 'productName'));
        // The first product alone carries 75 %, so the second completes class A.
        $this->assertSame(['A', 'A'], array_column($items, 'class'));
        $this->assertSame([150.0, 50.0], array_map(fn (array $i) => (float) $i['value'], $items));

        // The cumulative share only ever grows and lands on exactly 100 at the last row.
        $this->assertSame([75.0, 100.0], array_map(fn (array $i) => (float) $i['cumulativeShare'], $items));
        $this->assertEqualsWithDelta(100.0, array_sum(array_map(fn (array $i) => (float) $i['share'], $items)), 0.05);
    }

    public function testSuccessSummaryAddsUpToTheItems(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=revenue&currency=USD');
        $summary = $data['summary'];

        $this->assertSame(['A', 'B', 'C'], array_column($summary, 'class'), 'сводка всегда из трёх строк');
        $this->assertSame([2, 0, 0], array_column($summary, 'products'));
        $this->assertSame([200.0, 0.0, 0.0], array_map(fn (array $r) => (float) $r['value'], $summary));
        $this->assertEqualsWithDelta(100.0, array_sum(array_map(fn (array $r) => (float) $r['share'], $summary)), 0.05);
    }

    /**
     * A cheap product sold in bulk tops the quantity ranking and sinks in the revenue one.
     * Quantity spans both currencies because it is not money.
     */
    public function testSuccessQuantityRanksDifferentlyFromRevenue(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=quantity');

        $this->assertNull($data['baseCurrency'], 'количество — не деньги, валюты у него нет');
        $this->assertSame(65.0, (float) $data['total'], '40 + 15 + 10');
        $this->assertSame(
            ['Test Product UZS 1', 'Test Product USD 2', 'Test Product USD 1'],
            array_column($data['items'], 'productName'),
            'по количеству порядок обратный тому, что даёт выручка'
        );
        $this->assertSame(['A', 'A', 'B'], array_column($data['items'], 'class'));
        $this->assertSame('l', $data['items'][0]['unit']);
    }

    /** A UI that always fills the currency field must not break the quantity ranking. */
    public function testSuccessQuantityIgnoresACurrencyItWasGiven(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $withCurrency = $this->analyse($client, 'metric=quantity&currency=USD');

        $this->assertNull($withCurrency['baseCurrency']);
        $this->assertSame(65.0, (float) $withCurrency['total'], 'сумовая позиция осталась в рейтинге');
    }

    /**
     * "Test Product UZS 1" is sold at 6 000 against a cost of 40 000, so its profit is
     * negative. It must not eat into the total, and it belongs at the bottom in class C.
     */
    public function testSuccessLossMakingProductIsExcludedFromTheWhole(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=profit&currency=UZS');
        $items = $data['items'];

        $this->assertSame(0.0, (float) $data['total'], 'единственная сумовая позиция убыточна');
        $this->assertCount(1, $items);

        $loser = $items[0];
        $this->assertSame('Test Product UZS 1', $loser['productName']);
        $this->assertLessThan(0, (float) $loser['value']);
        $this->assertSame('0.00', $loser['share'], 'отрицательная доля бессмысленна');
        $this->assertSame('C', $loser['class']);
        $this->assertSame('100.00', $loser['cumulativeShare']);
    }

    public function testSuccessProfitIsRankedWithinItsCurrency(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=profit&currency=USD');

        $this->assertSame('USD', $data['baseCurrency']);
        $this->assertSame(120.0, (float) $data['total'], '90.00 + 30.00');
        $this->assertSame(['Test Product USD 2', 'Test Product USD 1'], array_column($data['items'], 'productName'));
    }

    /**
     * The profit metric selects the same lines the revenue one does — those sold in the given
     * currency — which means a dollar batch sold for sums has to have its profit converted
     * out of the cost currency first.
     */
    public function testSuccessProfitOfADollarBatchSoldForSums(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $saleIri = $this->createDraftSale($client, 'Test Client 1', '2026-09-10');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '10.000', '60000.00', 'UZS', '12500');
        $this->changeStatus($client, $saleIri, 'posted');

        $data = $this->analyse($client, 'metric=profit&currency=UZS');

        $this->assertSame('UZS', $data['baseCurrency']);
        $this->assertSame(100000.0, (float) $data['total'], '8.00 USD прибыли, приведённые к сумам');
        $this->assertSame('Test Product USD 2', $data['items'][0]['productName']);
    }

    public function testSuccessPinnedCurrencySkipsConversion(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'metric=revenue&currency=UZS');

        $this->assertSame('UZS', $data['baseCurrency']);
        $this->assertCount(1, $data['items'], 'долларовые позиции отфильтрованы');
        $this->assertSame('Test Product UZS 1', $data['items'][0]['productName']);
        $this->assertSame(240000.0, (float) $data['items'][0]['value'], 'сумы остались сумами');
        $this->assertSame('A', $data['items'][0]['class']);
    }

    public function testSuccessCategoryNarrowsTheSelection(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);
        $categoryId = basename($this->findIriBy(Category::class, ['name' => 'Test Category 1']));

        $data = $this->analyse($client, 'metric=revenue&currency=USD&category=' . $categoryId);

        $this->assertSame(
            ['Test Product USD 2', 'Test Product USD 1'],
            array_column($data['items'], 'productName')
        );
        $this->assertSame('Test Category 1', $data['items'][0]['categoryName']);
    }

    public function testSuccessThresholdsMoveProductsBetweenClasses(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        // At the default 80/95 both products are A. Lowering A to 60 pushes the second one
        // out of it, and B at 80 is still wide enough to catch it.
        $data = $this->analyse($client, 'metric=revenue&currency=USD&thresholdA=60&thresholdB=80');

        $this->assertSame(['A', 'B'], array_column($data['items'], 'class'));
    }

    public function testSuccessEmptyPeriodIsZeroed(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $this->sellTheThreeProducts($client);

        $data = $this->analyse($client, 'currency=USD&from=2000-01-01&to=2000-12-31');

        $this->assertSame([], $data['items']);
        $this->assertSame(0.0, (float) $data['total']);
        $this->assertSame(['0.00', '0.00', '0.00'], array_column($data['summary'], 'share'), 'без деления на ноль');
    }

    /**
     * @return array<string, string>
     */
    public static function invalidCriteria(): array
    {
        return [
            'unknown metric' => ['metric=nonsense', 'метрика'],
            'missing currency' => ['metric=revenue', 'Укажите валюту'],
            'missing currency for profit' => ['metric=profit', 'Укажите валюту'],
            'unknown currency' => ['currency=EUR', 'валюта'],
            'threshold A at zero' => ['currency=USD&thresholdA=0', 'по возрастанию'],
            'thresholds out of order' => ['currency=USD&thresholdA=90&thresholdB=80', 'по возрастанию'],
            'threshold B at hundred' => ['currency=USD&thresholdB=100', 'по возрастанию'],
            'non numeric threshold' => ['currency=USD&thresholdA=many', 'должен быть числом'],
            'wrong date format' => ['currency=USD&from=01-01-2025', 'ГГГГ-ММ-ДД'],
            'non numeric category' => ['currency=USD&category=abc', 'идентификатором'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCriteria')]
    public function testIncorrectCriteriaAreRefused(string $query, string $expectedInMessage): void
    {
        $client = $this->createAdminClientWithCredentials();

        $client->request(Request::METHOD_POST, '/api/products/abc-analysis?' . $query);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStringContainsString(
            $expectedInMessage,
            (string) (json_decode($client->getResponse()->getContent(false), true)['detail'] ?? '')
        );
    }

    /** Margins are the owner's business: the profit metric alone justifies ROLE_ADMIN. */
    public function testIncorrectForASeller(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/products/abc-analysis');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/products/abc-analysis');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyse(Client $client, string $query): array
    {
        $response = $client->request(Request::METHOD_POST, '/api/products/abc-analysis?' . $query);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray();
    }

    private function sellTheThreeProducts(Client $client): void
    {
        $saleIri = $this->createDraftSale($client, 'Test Client 1');
        $this->addSaleItem($client, $saleIri, 'Test Product USD 2', '15.000', '10.00');
        $this->addSaleItem($client, $saleIri, 'Test Product UZS 1', '40.000', '6000.00', 'UZS', self::RATE);
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent(false));
    }
}
