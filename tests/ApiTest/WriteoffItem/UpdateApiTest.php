<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\WriteoffItem;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiTest extends BaseApiTestCase
{
    public function testSuccessUpdateWriteoffItemQuantity(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['quantity' => '4.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['quantity' => '4.000']);
    }

    public function testIncorrectUpdateWriteoffItemWithNonPositiveQuantity(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['quantity' => '0']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** A posted writeoff is frozen. */
    public function testIncorrectUpdateWriteoffItemOfPostedWriteoff(): void
    {
        $client = $this->createAdminClientWithCredentials();
        $writeoffIri = $this->createDraftWriteoff($client);
        $item = $this->addWriteoffItem($client, $writeoffIri, 'Test Product USD 1', 'B-0002', '5.000');
        $this->changeStatus($client, $writeoffIri, 'posted');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(Request::METHOD_PATCH, $item['@id'], [
            'body' => json_encode(['quantity' => '4.000']),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
