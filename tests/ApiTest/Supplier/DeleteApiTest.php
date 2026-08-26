<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Supplier;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Supplier;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteSupplier(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createSupplierAndGetIri($client);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * receipts.supplier_id and batches.supplier_id restrict deletion, so a supplier that has
     * ever delivered anything must be refused with a business error.
     */
    public function testIncorrectDeleteSupplierThatHasReceipts(): void
    {
        $iri = $this->supplierIri('Test Supplier 1');

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /** Even an unposted receipt pins the supplier, because the row already references them. */
    public function testIncorrectDeleteSupplierWithOnlyADraftReceipt(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $iri = $this->createSupplierAndGetIri($client);

        $client->request(Request::METHOD_POST, '/api/receipts', [
            'body' => json_encode(['docDate' => '2026-08-01', 'supplier' => $iri]),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteSupplierByRole(): void
    {
        $iri = $this->findIriBy(Supplier::class, ['name' => 'Test Supplier 1']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * The fixture suppliers are referenced by receipts and batches, so delete a fresh one.
     * Takes the client instead of building its own: the assertion helpers always look at the
     * most recently created client, so mixing two of them inside one test misreads statuses.
     */
    private function createSupplierAndGetIri(Client $client): string
    {
        return $this->createAndGetIri($client, '/api/suppliers', [
            'name' => 'Supplier To Delete',
            'isActive' => true,
        ]);
    }
}
