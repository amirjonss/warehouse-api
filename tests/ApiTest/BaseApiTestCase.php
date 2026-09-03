<?php

declare(strict_types=1);

namespace App\Tests\ApiTest;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\UserFixtures;
use App\Component\Account\Enums\CashAccountKind;
use App\Component\Product\Enums\Currency;
use App\Entity\Batch;
use App\Entity\CashAccount;
use App\Entity\Category;
use App\Entity\Client as ClientEntity;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\Supplier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseApiTestCase extends ApiTestCase
{
    protected const JSON_LD = 'application/ld+json';
    protected const MERGE_PATCH = 'application/merge-patch+json';

    private ?string $refreshToken = null;
    private ?string $token = null;

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    protected function createAdminClientWithCredentials(): Client
    {
        return $this->createClientFor(UserFixtures::ADMIN_EMAIL);
    }

    protected function createSalesClientWithCredentials(): Client
    {
        return $this->createClientFor(UserFixtures::SALES_EMAIL);
    }

    /** A second ROLE_SALES identity, for "another user's record" checks. */
    protected function createSecondSalesClientWithCredentials(): Client
    {
        return $this->createClientFor(UserFixtures::SALES2_EMAIL);
    }

    /**
     * No Authorization header. ROLE_SALES is the lowest role in the hierarchy
     * (ROLE_ADMIN inherits it), so this is the only way to get a negative case
     * for endpoints guarded by is_granted('ROLE_SALES').
     */
    protected function createAnonymousClient(): Client
    {
        return static::createClient([], ['headers' => ['content-type' => self::JSON_LD]]);
    }

    /** Use other credentials if needed. */
    protected function getToken(array $body = []): string
    {
        $response = static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => $body ?: [
                'email' => UserFixtures::ADMIN_EMAIL,
                'password' => UserFixtures::PASSWORD,
            ],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        self::assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->token = $data['accessToken'];
        $this->refreshToken = $data['refreshToken'];

        return $data['accessToken'];
    }

    /**
     * Documents in this project move through statuses via a dedicated sub-resource
     * (POST /api/<resource>/{id}/change_status), which every document type shares.
     */
    protected function changeStatus(Client $client, string $iri, string $status): array
    {
        $response = $client->request(Request::METHOD_POST, $iri . '/change_status', [
            'body' => json_encode(['status' => $status]),
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        // Most documents answer with the whole entity, but the writeoff endpoint replies
        // with just the id, so the body is not always a JSON object.
        $decoded = json_decode($response->getContent(false), true);

        return is_array($decoded) ? $decoded : [];
    }

    // ---------------------------------------------------------------------
    // Fixture lookups. Ids are never stable (fixtures purge with DELETE and
    // Postgres sequences keep climbing), so everything is addressed by name.
    // ---------------------------------------------------------------------

    protected function productIri(string $name): string
    {
        return $this->findIriBy(Product::class, ['name' => $name]);
    }

    protected function clientIri(string $name): string
    {
        return $this->findIriBy(ClientEntity::class, ['name' => $name]);
    }

    protected function supplierIri(string $name): string
    {
        return $this->findIriBy(Supplier::class, ['name' => $name]);
    }

    protected function categoryIri(string $name): string
    {
        return $this->findIriBy(Category::class, ['name' => $name]);
    }

    protected function batchIri(string $productName, string $batchNumber): string
    {
        return $this->findIriBy(Batch::class, [
            'number' => $batchNumber,
            'product' => (int) basename($this->productIri($productName)),
        ]);
    }

    // ---------------------------------------------------------------------
    // Document builders. Every document in this domain follows the same
    // draft -> items -> change_status shape, so building one is worth sharing.
    // ---------------------------------------------------------------------

    protected function createDraftReceipt(Client $client, string $supplierName = 'Test Supplier 1', string $docDate = '2026-08-01'): string
    {
        return $this->createAndGetIri($client, '/api/receipts', [
            'docDate' => $docDate,
            'supplier' => $this->supplierIri($supplierName),
        ]);
    }

    protected function addReceiptItem(
        Client $client,
        string $receiptIri,
        string $productName,
        string $quantity,
        string $price,
        string $currency = 'USD',
        ?string $rate = '12500',
    ): array {
        $response = $client->request(Request::METHOD_POST, '/api/receipt_items', [
            'body' => json_encode([
                'receipt' => $receiptIri,
                'product' => $this->productIri($productName),
                'quantity' => $quantity,
                'price' => $price,
                'currency' => $currency,
                'rate' => $rate,
            ]),
        ]);

        return $response->toArray(false);
    }

    /** Draft receipt with a single line, already posted: the quickest way to get stock. */
    protected function receiveStock(Client $client, string $productName, string $quantity, string $price, string $currency = 'USD', ?string $rate = '12500'): string
    {
        $receiptIri = $this->createDraftReceipt($client);
        $this->addReceiptItem($client, $receiptIri, $productName, $quantity, $price, $currency, $rate);
        $this->changeStatus($client, $receiptIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return $receiptIri;
    }

    protected function createDraftSale(Client $client, string $clientName = 'Test Client 1', string $docDate = '2026-08-02'): string
    {
        return $this->createAndGetIri($client, '/api/sales', [
            'docDate' => $docDate,
            'customer' => $this->clientIri($clientName),
        ]);
    }

    protected function addSaleItem(
        Client $client,
        string $saleIri,
        string $productName,
        string $quantity,
        string $price,
        string $currency = 'USD',
        ?string $rate = null,
    ): array {
        $response = $client->request(Request::METHOD_POST, '/api/sale_items', [
            'body' => json_encode([
                'sale' => $saleIri,
                'product' => $this->productIri($productName),
                'quantity' => $quantity,
                'price' => $price,
                'currency' => $currency,
                'rate' => $rate,
            ]),
        ]);

        return $response->toArray(false);
    }

    protected function createDraftPayment(
        Client $client,
        string $clientName,
        string $amount,
        string $currency = 'USD',
        string $method = 'cash',
        string $docDate = '2026-08-03',
    ): string {
        return $this->createAndGetIri($client, '/api/payments', [
            'docDate' => $docDate,
            'client' => $this->clientIri($clientName),
            'amount' => $amount,
            'currency' => $currency,
            'method' => $method,
        ]);
    }

    protected function createDraftWriteoff(Client $client, string $reason = 'Expired', string $docDate = '2026-08-03'): string
    {
        return $this->createAndGetIri($client, '/api/writeoffs', [
            'docDate' => $docDate,
            'reason' => $reason,
        ]);
    }

    protected function createDraftInventory(
        Client $client,
        ?string $note = null,
        ?string $categoryName = null,
        string $docDate = '2026-08-03',
    ): string {
        $payload = ['docDate' => $docDate, 'note' => $note];
        if ($categoryName !== null) {
            $payload['category'] = $this->categoryIri($categoryName);
        }

        return $this->createAndGetIri($client, '/api/inventories', $payload);
    }

    /**
     * One counted batch. A null quantity is the "line created, nobody counted yet" state.
     */
    protected function addInventoryItem(
        Client $client,
        string $inventoryIri,
        string $productName,
        string $batchNumber,
        ?string $actualQty = null,
    ): array {
        $response = $client->request(Request::METHOD_POST, '/api/inventory_items', [
            'body' => json_encode([
                'inventory' => $inventoryIri,
                'product' => $this->productIri($productName),
                'batch' => $this->batchIri($productName, $batchNumber),
                'actualQty' => $actualQty,
            ]),
        ]);

        return $response->toArray(false);
    }

    protected function fillInventory(
        Client $client,
        string $inventoryIri,
        ?string $categoryName = null,
        bool $includeZeroStock = false,
    ): array {
        $payload = ['includeZeroStock' => $includeZeroStock];
        if ($categoryName !== null) {
            $payload['category'] = (int) basename($this->categoryIri($categoryName));
        }

        $response = $client->request(Request::METHOD_POST, $inventoryIri . '/fill', [
            'body' => json_encode($payload),
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        return $response->toArray(false);
    }

    /**
     * The lines of one count sheet, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function inventoryItems(Client $client, string $inventoryIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/inventory_items?inventory=' . basename($inventoryIri) . '&order[id]=asc'
        )->toArray(false)['member'] ?? [];
    }

    /**
     * FIFO layers of one sale line.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function saleItemAllocations(Client $client, string $saleItemIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations?saleItem=' . basename($saleItemIri)
        )->toArray()['member'];
    }

    /**
     * FIFO layers of a whole sale, across all of its lines.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function saleAllocations(Client $client, string $saleIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/sale_item_allocations?saleItem.sale=' . basename($saleIri)
        )->toArray()['member'];
    }

    /**
     * Writeoffs name the exact batch to take the goods from, so the batch is looked up
     * by its per-product number (B-0001, B-0002, ...).
     *
     * @return array<string, mixed>
     */
    protected function addWriteoffItem(
        Client $client,
        string $writeoffIri,
        string $productName,
        string $batchNumber,
        string $quantity,
    ): array {
        $productIri = $this->productIri($productName);

        // Resolved straight from the database rather than over HTTP: it keeps the helper
        // usable from the "wrong role" cases, which must not depend on a readable endpoint.
        $batchIri = $this->findIriBy(Batch::class, [
            'number' => $batchNumber,
            'product' => (int) basename($productIri),
        ]);

        $response = $client->request(Request::METHOD_POST, '/api/writeoff_items', [
            'body' => json_encode([
                'writeoff' => $writeoffIri,
                'product' => $productIri,
                'batch' => $batchIri,
                'quantity' => $quantity,
            ]),
        ]);

        return $response->toArray(false);
    }

    /**
     * Allocates part of a draft payment to the posted fixture sale SL-00001.
     *
     * @return array<string, mixed>
     */
    protected function allocate(
        Client $client,
        string $paymentIri,
        string $amountSpent,
        string $currency = 'USD',
        ?string $payRate = null,
        string $saleNumber = 'SL-00001',
    ): array {
        $response = $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $this->findIriBy(Sale::class, ['number' => $saleNumber]),
                'currency' => $currency,
                'amountSpent' => $amountSpent,
                'payRate' => $payRate,
                'isRounding' => false,
            ]),
        ]);

        return $response->toArray(false);
    }

    /**
     * An expense has to name the money it came out of: a seller's own open float, or —
     * for the owner — a company account passed here.
     */
    protected function createExpense(
        Client $client,
        string $docDate,
        string $amount,
        string $description = 'Test expense',
        string $currency = 'UZS',
        ?string $accountIri = null,
    ): string {
        return $this->createAndGetIri($client, '/api/expenses', [
            'docDate' => $docDate,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'account' => $accountIri,
        ]);
    }

    /**
     * Puts money on an account through the real opening-balance endpoint rather than a
     * fixture back door, so tests that spend it drive the production path.
     */
    protected function fund(Client $admin, string $accountIri, string $amount): array
    {
        return $admin->request(Request::METHOD_POST, $accountIri . '/opening_balance', [
            'body' => json_encode(['amount' => $amount, 'note' => 'Тестовый остаток']),
        ])->toArray(false);
    }

    // ---------------------------------------------------------------------
    // Cash sessions. Cash never moves without one: posting a cash payment
    // fails with 422 unless the person accepting it has an open session.
    // ---------------------------------------------------------------------

    /** Opens a session for whoever the client is authenticated as. */
    protected function openCashSession(Client $client): string
    {
        $response = $client->request(Request::METHOD_POST, '/api/cash_sessions', ['body' => json_encode([])]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent(false));

        return (string) $response->toArray()['@id'];
    }

    /** Balances and turnover of one session, as the summary sub-resource reports them. */
    protected function cashSummary(Client $client, string $sessionIri): array
    {
        return $client->request(Request::METHOD_POST, $sessionIri . '/summary', [
            'body' => json_encode([]),
        ])->toArray(false);
    }

    /**
     * Journal rows of one session, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cashEntries(Client $client, string $sessionIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/cash_entries?session=' . basename($sessionIri) . '&order[id]=asc'
        )->toArray(false)['member'] ?? [];
    }

    /**
     * The invariant the whole module rests on: the denormalised balance on the
     * session must equal the sum of its journal rows, per currency.
     */
    protected function assertCashJournalMatchesBalance(Client $client, string $sessionIri): void
    {
        $session = $client->request(Request::METHOD_GET, $sessionIri)->toArray();

        $sums = ['USD' => 0.0, 'UZS' => 0.0];
        foreach ($this->cashEntries($client, $sessionIri) as $entry) {
            $sums[$entry['currency']] += (float) $entry['amount'];
        }

        $this->assertSame(
            (float) $session['balanceUsd'],
            $sums['USD'],
            'USD: the journal drifted away from the session\'s denormalised balance'
        );
        $this->assertSame(
            (float) $session['balanceUzs'],
            $sums['UZS'],
            'UZS: the journal drifted away from the session\'s denormalised balance'
        );
    }

    /** A treasury account is addressed by its (kind, currency) pair, never by id. */
    protected function accountIri(string $kind, string $currency): string
    {
        $criteria = [
            'kind' => CashAccountKind::from($kind),
            'currency' => Currency::from($currency),
        ];

        $iri = $this->findIriBy(CashAccount::class, $criteria);

        // findIriBy leaves the account managed, and under test the API shares this
        // kernel's entity manager: a GET issued after money moved would otherwise be
        // served the stale copy, with the balance from before the write. Detaching it
        // sends every later read back to the database, so the helper is safe to call
        // before or after a write.
        $manager = static::getContainer()->get('doctrine')->getManager();
        $account = $manager->getRepository(CashAccount::class)->findOneBy($criteria);
        if ($account !== null) {
            $manager->detach($account);
        }

        return $iri;
    }

    protected function accountBalance(Client $client, string $accountIri): string
    {
        return (string) $client->request(Request::METHOD_GET, $accountIri)->toArray()['balance'];
    }

    /**
     * Journal rows of one account, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function accountEntries(Client $client, string $accountIri): array
    {
        return $client->request(
            Request::METHOD_GET,
            '/api/account_entries?account=' . basename($accountIri) . '&order[id]=asc'
        )->toArray(false)['member'] ?? [];
    }

    /**
     * The treasury counterpart of assertCashJournalMatchesBalance(): the denormalised
     * balance on the account must equal the sum of its journal. An account holds one
     * currency, so there is nothing to split here.
     */
    protected function assertAccountJournalMatchesBalance(Client $client, string $accountIri): void
    {
        $sum = 0.0;
        foreach ($this->accountEntries($client, $accountIri) as $entry) {
            $sum += (float) $entry['amount'];
        }

        $this->assertSame(
            (float) $this->accountBalance($client, $accountIri),
            $sum,
            'the journal drifted away from the account\'s denormalised balance'
        );
    }

    /**
     * Constraint violations of the last response.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getResponseViolations(): array
    {
        $content = json_decode(self::getClient()->getResponse()->getContent(), true);

        return $content['violations'] ?? [];
    }

    /** POSTs a payload, asserts it was created and hands back the new resource's IRI. */
    protected function createAndGetIri(Client $client, string $uri, array $payload): string
    {
        $response = $client->request(Request::METHOD_POST, $uri, ['body' => json_encode($payload)]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $response->toArray()['@id'];
    }

    private function createClientFor(string $email): Client
    {
        $this->getToken([
            'email' => $email,
            'password' => UserFixtures::PASSWORD,
        ]);

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $this->token,
                'content-type' => self::JSON_LD,
            ],
        ]);
    }
}
