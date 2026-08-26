<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Supplier;

use App\Entity\Supplier;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateSupplier(): void
    {
        $iri = $this->findIriBy(Supplier::class, ['name' => 'Test Supplier 1']);
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['phone' => '+998907650000']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'phone' => '+998907650000']);
    }

    public function testIncorrectUpdateSupplierByRole(): void
    {
        $iri = $this->findIriBy(Supplier::class, ['name' => 'Test Supplier 1']);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['phone' => '+998907650000']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
