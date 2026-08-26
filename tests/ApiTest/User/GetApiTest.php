<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetApiTest extends BaseApiTestCase
{
    public function testSuccessGetUserCollection(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $emails = array_column($response->toArray()['member'], 'email');
        $this->assertContains(UserFixtures::ADMIN_EMAIL, $emails);
        $this->assertContains(UserFixtures::SALES_EMAIL, $emails);
    }

    public function testPasswordIsNeverExposed(): void
    {
        $response = $this->createAdminClientWithCredentials()->request(Request::METHOD_GET, '/api/users');

        foreach ($response->toArray()['member'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
        }
    }

    /** Listing every user is admin-only. */
    public function testIncorrectGetUserCollectionByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, '/api/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSuccessGetOwnUserItemBySalesRole(): void
    {
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_GET, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains(['@id' => $iri, 'email' => UserFixtures::SALES_EMAIL]);
    }

    public function testIncorrectGetUserCollectionAnonymously(): void
    {
        $this->createAnonymousClient()->request(Request::METHOD_GET, '/api/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
