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
        $client = $this->createSalesClientWithCredentials();
        $iri = $this->createExpense($client, '2026-08-25', '100.00');

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testIncorrectDeleteExpenseAnonymously(): void
    {
        $iri = $this->createExpense($this->createSalesClientWithCredentials(), '2026-08-25', '100.00');

        $this->createAnonymousClient()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
