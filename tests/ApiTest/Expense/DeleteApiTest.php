<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteExpense(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $account = $this->accountIri('cash', 'UZS');
        $this->fund($client, $account, '1000000.00');
        $iri = $this->createExpense($client, '2026-08-25', '100.00', 'Test expense', 'UZS', $account);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Deleting puts the money back with an opposite row, never by rewriting one.
        $this->assertSame(1000000.0, (float) $this->accountBalance($client, $account));
        $this->assertAccountJournalMatchesBalance($client, $account);
    }

    public function testIncorrectDeleteExpenseAnonymously(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $account = $this->accountIri('cash', 'UZS');
        $this->fund($client, $account, '1000000.00');
        $iri = $this->createExpense($client, '2026-08-25', '100.00', 'Test expense', 'UZS', $account);

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
