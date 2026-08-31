<?php

declare(strict_types=1);

namespace App\Tests\ApiTest\MoneyTransfer;

use App\Tests\ApiTest\Wallet\WalletTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteApiTest extends WalletTestCase
{
    public function testSuccessDeleteDraft(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $iri = $this->createDraftTransfer(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '100000.00'
        );

        $admin->request(Request::METHOD_DELETE, $iri);

        $this->assertStatus(Response::HTTP_NO_CONTENT, $admin);
    }

    /** A posted transfer moved money: deleting it would erase the journal's counterpart. */
    public function testIncorrectDeletePosted(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $cashIri = $this->accountIri('cash', 'UZS');
        $this->fund($admin, $cashIri, '10000000.00');
        $iri = $this->postTransfer($admin, $cashIri, $this->accountIri('bank', 'UZS'), '100000.00');

        $admin->request(Request::METHOD_DELETE, $iri);

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, $admin);
    }

    public function testIncorrectDeleteAsSales(): void
    {
        $admin = $this->createAdminClientWithCredentials();
        $iri = $this->createDraftTransfer(
            $admin,
            $this->accountIri('cash', 'UZS'),
            $this->accountIri('bank', 'UZS'),
            '100000.00'
        );

        $sales = $this->createSalesClientWithCredentials();
        $sales->request(Request::METHOD_DELETE, $iri);

        $this->assertStatus(Response::HTTP_FORBIDDEN, $sales);
    }
}
