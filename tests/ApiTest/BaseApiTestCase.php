<?php

declare(strict_types=1);

namespace App\Tests\ApiTest;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\UserFixtures;
use App\Entity\Batch;
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

        // Resolved straight from the database rather than over HTTP: /api/batches is
        // admin-only, and this helper is also used to drive the "wrong role" cases.
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

    protected function createExpense(
        Client $client,
        string $docDate,
        string $amount,
        string $description = 'Test expense',
        string $currency = 'UZS',
    ): string {
        return $this->createAndGetIri($client, '/api/expenses', [
            'docDate' => $docDate,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
        ]);
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
