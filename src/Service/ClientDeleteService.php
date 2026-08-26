<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Client\Exceptions\ClientInUseException;
use App\Entity\Client;
use App\Repository\DebtRepository;
use App\Repository\PaymentRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ClientDeleteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRepository $saleRepository,
        private PaymentRepository $paymentRepository,
        private DebtRepository $debtRepository,
    ) {
    }

    public function delete(Client $client): void
    {
        // sales.customer_id, payments.client_id and debts.client_id are all NOT NULL with a
        // restricting foreign key, so deleting a client with any history would surface as a
        // raw constraint violation from the database.
        // Note the sale side is mapped as "customer", not "client".
        $blockers = [
            'sale(s)' => $this->saleRepository->count(['customer' => $client]),
            'payment(s)' => $this->paymentRepository->count(['client' => $client]),
            'debt record(s)' => $this->debtRepository->count(['client' => $client]),
        ];

        $used = array_filter($blockers);
        if ($used === []) {
            $this->entityManager->remove($client);
            $this->entityManager->flush();

            return;
        }

        $parts = [];
        foreach ($used as $label => $count) {
            $parts[] = $count . ' ' . $label;
        }

        throw new ClientInUseException(sprintf(
            'Client "%s" still has %s and cannot be deleted.',
            $client->getName(),
            implode(', ', $parts)
        ));
    }
}
