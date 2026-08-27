<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\User;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends BaseApiTestCase
{
    /** Users are soft-deleted, and the read extension then hides them. */
    public function testSuccessSoftDeleteUser(): void
    {
        $client = $this->createAdminClientWithCredentials();
        [$iri] = $this->createUser($client);

        $client->request(Request::METHOD_DELETE, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, $iri);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** A deleted account must no longer be able to log in. */
    public function testDeletedUserCannotAuthenticate(): void
    {
        $client = $this->createAdminClientWithCredentials();
        [$iri, $password] = $this->createUser($client);
        $email = $client->request(Request::METHOD_GET, $iri)->toArray()['email'];

        // The freshly created account can log in...
        static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => ['email' => $email, 'password' => $password],
            'headers' => ['content-type' => self::JSON_LD],
        ]);
        $this->assertResponseIsSuccessful();

        // assertResponseStatusCodeSame() always inspects the most recently created client,
        // and an extra one was just created for the login above, so this DELETE is checked
        // on its own response object instead.
        $deleteResponse = $client->request(Request::METHOD_DELETE, $iri);
        $this->assertSame(Response::HTTP_NO_CONTENT, $deleteResponse->getStatusCode());

        // ...but not once it has been soft-deleted.
        static::createClient()->request(Request::METHOD_POST, '/api/users/auth', [
            'json' => ['email' => $email, 'password' => $password],
            'headers' => ['content-type' => self::JSON_LD],
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testIncorrectDeleteAnotherUserByRole(): void
    {
        $iri = $this->findIriBy(User::class, ['email' => UserFixtures::ADMIN_EMAIL]);

        $this->createSalesClientWithCredentials()->request(Request::METHOD_DELETE, $iri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * POST /api/users answers with a UserCreatedDto (so that the generated password can be
     * shown once), and its @id is a genid rather than the user's IRI — hence the lookup.
     *
     * @return array{0: string, 1: string} the user's IRI and the generated password
     */
    private function createUser(Client $client): array
    {
        $email = sprintf('to-delete-%s@example.com', uniqid('', true));

        $response = $client->request(Request::METHOD_POST, '/api/users', [
            'body' => json_encode([
                'email' => $email,
                'firstName' => 'Doomed',
                'roles' => ['ROLE_SALES'],
            ]),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return [$this->findIriBy(User::class, ['email' => $email]), $response->toArray()['password']];
    }
}
