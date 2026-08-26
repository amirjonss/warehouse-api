<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Category;

use App\Entity\Category;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateCategory(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['name' => 'Updated Category Name']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'name' => 'Updated Category Name']);
    }

    public function testIncorrectUpdateCategoryToABlankName(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['name' => '']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectUpdateCategoryByRole(): void
    {
        $iri = $this->findIriBy(Category::class, ['name' => 'Test Category 1']);
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_PATCH,
            $iri,
            [
                'body' => json_encode(['name' => 'Updated Category Name']),
                'headers' => ['content-type' => self::MERGE_PATCH],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
