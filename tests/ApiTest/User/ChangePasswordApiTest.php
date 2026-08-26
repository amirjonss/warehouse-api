<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordApiTest extends BaseApiTestCase
{
    public function testSuccessChangeOwnPassword(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $client->request(Request::METHOD_PATCH, $iri . '/password', [
            'body' => json_encode([
                'currentPassword' => UserFixtures::PASSWORD,
                'password' => 'NewSecret456!',
            ]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseIsSuccessful();

        // The new password works...
        $this->getToken(['email' => UserFixtures::SALES_EMAIL, 'password' => 'NewSecret456!']);
        $this->assertResponseIsSuccessful();
    }

    public function testOldPasswordStopsWorkingAfterTheChange(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $client->request(Request::METHOD_PATCH, $iri . '/password', [
            'body' => json_encode([
                'currentPassword' => UserFixtures::PASSWORD,
                'password' => 'NewSecret456!',
            ]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);
        $this->assertResponseIsSuccessful();

        static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => ['email' => UserFixtures::SALES_EMAIL, 'password' => UserFixtures::PASSWORD],
            'headers' => ['content-type' => self::JSON_LD],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testIncorrectChangePasswordWithWrongCurrentPassword(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $client->request(Request::METHOD_PATCH, $iri . '/password', [
            'body' => json_encode([
                'currentPassword' => 'wrong-password',
                'password' => 'NewSecret456!',
            ]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /** Passwords must be at least 6 characters long. */
    public function testIncorrectChangePasswordToATooShortOne(): void
    {
        $client = $this->createSalesClientWithCredentials();
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $client->request(Request::METHOD_PATCH, $iri . '/password', [
            'body' => json_encode([
                'currentPassword' => UserFixtures::PASSWORD,
                'password' => 'abc',
            ]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** One user must not be able to change another user's password. */
    public function testIncorrectChangeAnotherUsersPassword(): void
    {
        $client = $this->createSecondSalesClientWithCredentials();
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::SALES_EMAIL]);

        $client->request(Request::METHOD_PATCH, $iri . '/password', [
            'body' => json_encode([
                'currentPassword' => UserFixtures::PASSWORD,
                'password' => 'NewSecret456!',
            ]),
            'headers' => ['content-type' => self::MERGE_PATCH],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
