<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Wallet;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\ApiTest\CashSession\CashTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The treasury is admin-only, while money still enters the system through a seller, so
 * these tests almost always juggle two clients. Everything that gets money onto an
 * account lives here; the assertions themselves stay about the treasury.
 */
abstract class WalletTestCase extends CashTestCase
{
    /** @return string IRI of the draft transfer */
    protected function createDraftTransfer(
        Client $admin,
        string $fromAccountIri,
        string $toAccountIri,
        string $amountSent,
        ?string $amountReceived = null,
        ?string $rate = null,
        string $docDate = '2026-08-20',
    ): string {
        return $this->createAndGetIri($admin, '/api/money_transfers', [
            'docDate' => $docDate,
            'fromAccount' => $fromAccountIri,
            'toAccount' => $toAccountIri,
            'amountSent' => $amountSent,
            'amountReceived' => $amountReceived ?? $amountSent,
            'rate' => $rate,
        ]);
    }

    /** Creates and posts a transfer in one go. */
    protected function postTransfer(
        Client $admin,
        string $fromAccountIri,
        string $toAccountIri,
        string $amountSent,
        ?string $amountReceived = null,
        ?string $rate = null,
    ): string {
        $iri = $this->createDraftTransfer($admin, $fromAccountIri, $toAccountIri, $amountSent, $amountReceived, $rate);
        $this->changeStatus($admin, $iri, 'posted');
        $this->assertStatus(Response::HTTP_OK, $admin);

        return $iri;
    }

    /** The response body of a failed request, for message assertions. */
    protected function attemptTransferStatus(Client $admin, string $transferIri, string $status): void
    {
        $this->changeStatus($admin, $transferIri, $status);
    }
}
