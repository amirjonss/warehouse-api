<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Client;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    private const PAYLOAD = [
        'name' => 'New Client',
        'contact' => 'Ivan Ivanov',
        'phone' => '+998901234567',
        'address' => 'Tashkent, Mirzo-Ulugbek 1',
        'isActive' => true,
    ];

    public function testSuccessCreateClientBySalesRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/clients',
            ['body' => json_encode(self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains(['name' => 'New Client', 'debtUsd' => '0.00', 'debtUzs' => '0.00']);
    }

    /** debtUsd/debtUzs are derived from the debt ledger and must not be settable from outside. */
    public function testDebtFieldsAreNotWritable(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/clients',
            ['body' => json_encode(self::PAYLOAD + ['debtUsd' => '999.00', 'debtUzs' => '999.00'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains(['debtUsd' => '0.00', 'debtUzs' => '0.00']);
    }

    public function testIncorrectCreateClientWithBlankName(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/clients',
            ['body' => json_encode(['name' => ''] + self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'name']]]);
    }

    public function testIncorrectCreateClientWithoutRequiredFields(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/clients',
            ['body' => json_encode([])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateClientAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/clients',
            ['body' => json_encode(self::PAYLOAD)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
