<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Sale;

use App\Entity\Sale;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetSaleCollection(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/sales');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        // One posted and one draft sale come from the fixtures.
        $this->assertSame(2, $response->toArray()['totalItems']);
    }

    /**
     * outstandingUsd/outstandingUzs are not stored: a dedicated state provider computes
     * them from the debt ledger and attaches them to the serialized sale.
     */
    public function testSuccessGetSaleItemShowsOutstandingDebt(): void
    {
        $iri = $this->findIriBy(Sale::class, ['number' => 'SL-00001']);

        $response = $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $response->toArray();
        $this->assertSame('posted', $data['status']);
        $this->assertSame(50.0, (float) $data['totalUsd']);
        // Nothing has been paid yet, so the whole total is still outstanding.
        $this->assertSame(50.0, (float) $data['outstandingUsd']);
        $this->assertSame(0.0, (float) $data['outstandingUzs']);
    }

    public function testSuccessGetSaleCollectionAlsoCarriesOutstanding(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/sales?number=SL-00001'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $member = $response->toArray()['member'];
        $this->assertCount(1, $member);
        $this->assertSame(50.0, (float) $member[0]['outstandingUsd']);
    }

    public function testSuccessFilterSalesByCustomer(): void
    {
        $customerIri = $this->clientIri('Test Client 2');

        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_GET,
            '/api/sales?customer=' . basename($customerIri)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame(['SL-00001'], array_column($response->toArray()['member'], 'number'));
    }

    public function testIncorrectGetSaleCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/sales');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
