<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Product;

use App\Entity\Product;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateProduct(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product USD 1']);
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['priceUsd' => '47.00']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'priceUsd' => '47.00']);
    }

    public function testIncorrectUpdateProductByRole(): void
    {
        $iri = $this->findIriBy(Product::class, ['name' => 'Test Product USD 1']);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['priceUsd' => '47.00']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
