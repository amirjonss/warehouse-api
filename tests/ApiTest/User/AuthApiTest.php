<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\DataFixtures\UserFixtures;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthApiTest extends BaseApiTestCase
{
    public function testSuccessAuth(): void
    {
        $response = static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => [
                'email' => UserFixtures::ADMIN_EMAIL,
                'password' => UserFixtures::PASSWORD,
            ],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertNotSame('', $data['accessToken']);
    }

    public function testFailAuthWithWrongPassword(): void
    {
        static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => [
                'email' => UserFixtures::ADMIN_EMAIL,
                'password' => 'wrong-password',
            ],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testFailAuthWithUnknownEmail(): void
    {
        static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => [
                'email' => 'nobody@example.com',
                'password' => UserFixtures::PASSWORD,
            ],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAuthorizedRequestReachesProtectedEndpoint(): void
    {
        $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/clients');

        $this->assertResponseIsSuccessful();
    }

    public function testAnonymousRequestIsRejected(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/clients');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
