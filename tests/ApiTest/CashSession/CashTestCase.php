<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\CashSession;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cash needs money, and money only enters the system through a posted payment against a
 * real debt. So nearly every test starts with a sale on credit and its settlement; that
 * part lives here so the assertions themselves stay readable.
 */
abstract class CashTestCase extends BaseApiTestCase
{
    protected const CUSTOMER = 'Test Client 1';

    /**
     * assertResponseStatusCodeSame() looks at the most recently created client, not at the
     * one request() was called on. Cash tests almost always have two of them, the seller
     * and the owner, so the status is asserted against a named client.
     */
    protected function assertStatus(int $expected, Client $client): void
    {
        $response = $client->getResponse();

        // Without false, getContent() throws on 4xx, and the error text is what we want.
        $this->assertSame($expected, $response->getStatusCode(), $response->getContent(false));
    }

    /** The human-readable explanation from the problem+json of the client's last response. */
    protected function detail(Client $client): string
    {
        return (string) (json_decode($client->getResponse()->getContent(false), true)['detail'] ?? '');
    }

    /** Same as in the base class, but the status is asserted on the client passed in. */
    protected function createAndGetIri(Client $client, string $uri, array $payload): string
    {
        $response = $client->request(Request::METHOD_POST, $uri, ['body' => json_encode($payload)]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent(false));

        return (string) $response->toArray()['@id'];
    }

    /** A sale on credit: returns the IRI of the posted sale. */
    protected function sellOnCredit(
        Client $client,
        string $amount,
        string $currency = 'USD',
        string $customer = self::CUSTOMER,
    ): string {
        [$product, $quantity, $price] = $currency === 'USD'
            ? ['Test Product USD 1', '1.000', $amount]
            : ['Test Product UZS 1', '1.000', $amount];

        $saleIri = $this->createDraftSale($client, $customer);
        $this->addSaleItem($client, $saleIri, $product, $quantity, $price, $currency);
        $this->changeStatus($client, $saleIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $client);

        return $saleIri;
    }

    /**
     * Sell on credit and settle the debt right away: the shortest way to put cash into a
     * session. Returns the IRI of the posted payment.
     */
    protected function collect(
        Client $client,
        string $amount,
        string $currency = 'USD',
        string $method = 'cash',
        string $customer = self::CUSTOMER,
    ): string {
        $saleIri = $this->sellOnCredit($client, $amount, $currency, $customer);

        return $this->settle($client, $saleIri, $amount, $currency, $method, $customer);
    }

    /** Settles an existing sale. Returns the IRI of the posted payment. */
    protected function settle(
        Client $client,
        string $saleIri,
        string $amount,
        string $currency = 'USD',
        string $method = 'cash',
        string $customer = self::CUSTOMER,
    ): string {
        $paymentIri = $this->createDraftPayment($client, $customer, $amount, $currency, $method);
        $this->allocateTo($client, $paymentIri, $saleIri, $amount, $currency);
        $this->changeStatus($client, $paymentIri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $client);

        return $paymentIri;
    }

    protected function allocateTo(
        Client $client,
        string $paymentIri,
        string $saleIri,
        string $amount,
        string $currency = 'USD',
    ): void {
        $client->request(Request::METHOD_POST, '/api/payment_allocations', [
            'body' => json_encode([
                'payment' => $paymentIri,
                'sale' => $saleIri,
                'currency' => $currency,
                'amountSpent' => $amount,
                'isRounding' => false,
            ]),
        ]);
        $this->assertStatus(Response::HTTP_CREATED, $client);
    }

    /** Declares that money was handed over to the owner. */
    protected function declareHandover(
        Client $client,
        string $sessionIri,
        string $amount,
        string $currency = 'USD',
        ?string $note = null,
    ): array {
        $response = $client->request(Request::METHOD_POST, $sessionIri . '/handover', [
            'body' => json_encode(['amount' => $amount, 'currency' => $currency, 'note' => $note]),
        ]);

        return $response->toArray(false);
    }

    protected function confirmHandover(Client $client, string $entryIri): array
    {
        return $client->request(Request::METHOD_POST, $entryIri . '/confirm', [
            'body' => json_encode([]),
        ])->toArray(false);
    }

    protected function closeSession(
        Client $client,
        string $sessionIri,
        string $acceptedUsd = '0',
        string $acceptedUzs = '0',
        ?string $note = null,
    ): array {
        return $client->request(Request::METHOD_POST, $sessionIri . '/close', [
            'body' => json_encode([
                'acceptedUsd' => $acceptedUsd,
                'acceptedUzs' => $acceptedUzs,
                'note' => $note,
            ]),
        ])->toArray(false);
    }

    /** @return array<int, array<string, mixed>> journal rows of a single kind */
    protected function entriesOfKind(Client $client, string $sessionIri, string $kind): array
    {
        return array_values(array_filter(
            $this->cashEntries($client, $sessionIri),
            static fn (array $entry): bool => $entry['kind'] === $kind
        ));
    }

    /**
     * A relation in the response is either an IRI string or an embedded object, depending
     * on whether the related entity's fields made it into the serialisation group.
     */
    protected function iriOf(string|array|null $relation): ?string
    {
        return is_array($relation) ? ($relation['@id'] ?? null) : $relation;
    }

    protected function session(Client $client, string $sessionIri): array
    {
        return $client->request(Request::METHOD_GET, $sessionIri)->toArray();
    }
}
