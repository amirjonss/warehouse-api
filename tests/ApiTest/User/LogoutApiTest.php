<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Logout bumps the user's tokenVersion, which revokes every token issued so far. */
class LogoutApiTest extends BaseApiTestCase
{
    public function testSuccessLogoutRevokesTheAccessToken(): void
    {
        $client = $this->createSalesClientWithCredentials();

        $client->request(Request::METHOD_POST, '/api/users/about_me');
        $this->assertResponseIsSuccessful();

        $client->request(Request::METHOD_POST, '/api/users/logout');
        $this->assertResponseIsSuccessful();

        // The very same client now carries a token the provider refuses.
        $client->request(Request::METHOD_POST, '/api/users/about_me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testIncorrectLogoutAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/users/logout');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
