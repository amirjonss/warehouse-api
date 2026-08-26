<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateApiTest extends BaseApiTestCase
{
    /** The password is generated server side and returned once, in the create response. */
    public function testSuccessCreateUser(): void
    {
        $email = $this->uniqueEmail();

        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users',
            ['body' => json_encode([
                'email' => $email,
                'firstName' => 'New',
                'lastName' => 'User',
                'roles' => ['ROLE_SALES'],
            ])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame($email, $data['email']);
        $this->assertNotEmpty($data['password']);
    }

    /** The generated password has to actually work for logging in. */
    public function testGeneratedPasswordCanBeUsedToAuthenticate(): void
    {
        $email = $this->uniqueEmail();

        $response = $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users',
            ['body' => json_encode([
                'email' => $email,
                'firstName' => 'New',
                'lastName' => 'User',
                'roles' => ['ROLE_SALES'],
            ])]
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->getToken(['email' => $email, 'password' => $response->toArray()['password']]);
        $this->assertResponseIsSuccessful();
    }

    public function testIncorrectCreateUserWithInvalidEmail(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users',
            ['body' => json_encode([
                'email' => 'not-an-email',
                'firstName' => 'New',
                'roles' => ['ROLE_SALES'],
            ])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'email']]]);
    }

    public function testIncorrectCreateUserWithBlankFirstName(): void
    {
        $this->createAdminClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users',
            ['body' => json_encode([
                'email' => $this->uniqueEmail(),
                'firstName' => '',
                'roles' => ['ROLE_SALES'],
            ])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'firstName']]]);
    }

    public function testIncorrectCreateUserWithDuplicateEmail(): void
    {
        $email = $this->uniqueEmail();
        $payload = json_encode([
            'email' => $email,
            'firstName' => 'New',
            'roles' => ['ROLE_SALES'],
        ]);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_POST, '/api/users', ['body' => $payload]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->createAdminClientWithCredentials()->request(Request::METHOD_POST, '/api/users', ['body' => $payload]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectCreateUserByRole(): void
    {
        $this->createSalesClientWithCredentials()->request(
            Request::METHOD_POST,
            '/api/users',
            ['body' => json_encode([
                'email' => $this->uniqueEmail(),
                'firstName' => 'New',
                'roles' => ['ROLE_SALES'],
            ])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function uniqueEmail(): string
    {
        return sprintf('created-%s@example.com', uniqid('', true));
    }
}
