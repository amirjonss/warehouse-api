<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Receipt;

use App\Entity\Receipt;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    public function testSuccessDeleteDraftReceipt(): void
    {
        $iri = $this->createDraftReceipt($this->createAdminClientWithCredentials());

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** Once a receipt is posted it owns batches and stock movements, so it may not be deleted. */
    public function testIncorrectDeletePostedReceipt(): void
    {
        $iri = $this->findIriBy(Receipt::class, ['number' => 'RC-00001']);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectDeleteReceiptByRole(): void
    {
        $iri = $this->findIriBy(Receipt::class, ['number' => 'RC-00001']);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

}
