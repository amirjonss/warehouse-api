<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\DataFixtures\UserFixtures;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** This endpoint is deliberately public: the sign-up form calls it before submitting. */
class IsUniqueEmailApiTest extends BaseApiTestCase
{
    public function testTakenEmailIsNotUnique(): void
    {
        $response = static::createClient()->request(Request::METHOD_POST, '/api/users/is_unique_email', [
            'json' => ['email' => UserFixtures::ADMIN_EMAIL],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertFalse($response->toArray()['isUnique']);
    }

    public function testFreeEmailIsUnique(): void
    {
        $response = static::createClient()->request(Request::METHOD_POST, '/api/users/is_unique_email', [
            'json' => ['email' => 'nobody-' . uniqid('', true) . '@example.com'],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue($response->toArray()['isUnique']);
    }
}
