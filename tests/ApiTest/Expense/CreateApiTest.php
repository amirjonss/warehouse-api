<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\Expense;

use App\Tests\ApiTest\BaseApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An expense has to say where the money came from. A seller spends out of their own open
 * float; the owner names a company account. Without a source the expense would reduce
 * profit while taking money off nothing, overstating the company's cash for good.
 */
class CreateApiTest extends BaseApiTestCase
{
    public function testSuccessOwnerCreatesExpenseFromACompanyAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $accountIri, '1000000.00');

        $response = $admin->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '100.00',
                'currency' => 'UZS',
                'account' => $accountIri,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $response->toArray();

        $this->assertSame('Fuel', $data['description']);
        $this->assertSame('100.00', $data['amount']);
        $this->assertSame('UZS', $data['currency']);
        // createdBy comes from the token, not the payload.
        $this->assertSame('admin', $data['createdBy']['firstName']);

        $this->assertSame(999900.0, (float) $this->accountBalance($admin, $accountIri));
        $this->assertAccountJournalMatchesBalance($admin, $accountIri);
    }

    /** The money never left anything, so the document must not exist either. */
    public function testIncorrectSellerCreatesExpenseWithoutAnOpenShift(): void
    {
        $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '100.00',
                'currency' => 'UZS',
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['detail' => 'Нельзя провести расход: у сотрудника sales salesov нет открытой смены. Откройте смену и повторите.']);
    }

    /** The company's accounts are the owner's; a seller spends what is in their bag. */
    public function testIncorrectSellerSpendsFromACompanyAccount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $accountIri, '1000000.00');

        $this->createSalesClientWithCredentials()->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '100.00',
                'currency' => 'UZS',
                'account' => $accountIri,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIncorrectExpenseInACurrencyTheAccountDoesNotHold(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $accountIri, '1000000.00');

        $admin->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '10.00',
                'currency' => 'USD',
                'account' => $accountIri,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIncorrectExpenseOverTheAccountBalance(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $accountIri, '5000.00');

        $admin->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '9000.00',
                'currency' => 'UZS',
                'account' => $accountIri,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(5000.0, (float) $this->accountBalance($admin, $accountIri));
    }

    public function testIncorrectCreateExpenseWithNonPositiveAmount(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $accountIri = $this->accountIri('cash', 'UZS');

        $admin->request(Request::METHOD_POST, '/api/expenses', [
            'body' => json_encode([
                'docDate' => '2026-08-25',
                'description' => 'Fuel',
                'amount' => '-5.00',
                'currency' => 'UZS',
                'account' => $accountIri,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'amount']]]);
    }

    public function testIncorrectCreateExpenseAnonymously(): void
    {
        $this->createAnonymousClient()->request(
            Request::METHOD_POST,
            '/api/expenses',
            ['body' => json_encode(['docDate' => '2026-08-25', 'description' => 'Fuel', 'amount' => '100.00', 'currency' => 'UZS'])]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
