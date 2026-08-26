<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthByRefreshTokenApiTest extends BaseApiTestCase
{
    public function testSuccessAuthByRefreshToken(): void
    {
        $this->getToken();

        $response = static::createClient()->request(Request::METHOD_POST, '/api/users/auth/refreshToken', [
            'json' => ['refreshToken' => $this->getRefreshToken()],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);
    }

    public function testIncorrectAuthByGarbageRefreshToken(): void
    {
        static::createClient()->request(Request::METHOD_POST, '/api/users/auth/refreshToken', [
            'json' => ['refreshToken' => 'not-a-token'],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /** Logging out bumps the token version, which must invalidate the old refresh token. */
    public function testRefreshTokenStopsWorkingAfterLogout(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $refreshToken = $this->getRefreshToken();

        $client->request(Request::METHOD_POST, '/api/users/logout');
        $this->assertResponseIsSuccessful();

        static::createClient()->request(Request::METHOD_POST, '/api/users/auth/refreshToken', [
            'json' => ['refreshToken' => $refreshToken],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
