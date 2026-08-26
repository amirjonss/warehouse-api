<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Entity\Client;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateClientBySalesRole(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 1']);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['phone' => '+998901112233']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'phone' => '+998901112233']);
    }

    /** The running debt balance must stay under the ledger's control. */
    public function testDebtCannotBeChangedThroughPatch(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 2']);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['debtUsd' => '0.00']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'debtUsd' => '50.00']);
    }

    public function testIncorrectUpdateClientAnonymously(): void
    {
        $iri = $this->findIriBy(Client::class, ['name' => 'Test Client 1']);
        $this->createAnonymousClient()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['phone' => '+998901112233']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
