<?php

namespace App\Component\Debt;

use App\Component\Core\Enums\DocumentType;
use App\Component\Product\Enums\Currency;
use App\Entity\Client;
use App\Entity\Debt;
use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\User;
use DateTime;

class DebtFactory
{
    public function create(
        DocumentType $docType,
        Client $client,
        Sale $sale,
        ?Payment $payment,
        string $amount,
        Currency $currency,
        User $createdBy
    ): Debt {
        $debt = new Debt();
        $debt
            ->setOccurredAt(new DateTime())
            ->setDocType($docType)
            ->setClient($client)
            ->setSale($sale)
            ->setPayment($payment)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setCreatedBy($createdBy);

        if ($currency === Currency::USD) {
            $client->setDebtUsd(bcadd($client->getDebtUsd() ?? '0', $amount, 2));
        } else {
            $client->setDebtUzs(bcadd($client->getDebtUzs() ?? '0', $amount, 2));
        }

        return $debt;
    }
}
