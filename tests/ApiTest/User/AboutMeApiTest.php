<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\DataFixtures\UserFixtures;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AboutMeApiTest extends BaseApiTestCase
{
    public function testSuccessAboutMe(): void
    {
        $response = $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users/about_me'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame(UserFixtures::SALES_EMAIL, $data['email']);
        $this->assertContains('ROLE_SALES', $data['roles']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function testAdminSeesTheirOwnRecord(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users/about_me'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(UserFixtures::ADMIN_EMAIL, $response->toArray()['email']);
    }

    public function testIncorrectAboutMeAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_POST, '/api/users/about_me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
