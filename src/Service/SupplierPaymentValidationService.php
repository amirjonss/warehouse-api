<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\SupplierPayment\Exceptions\AccountCurrencyMismatchException;
use App\Entity\SupplierPayment;

class SupplierPaymentValidationService
{
    public function validate(SupplierPayment $data): void
    {
        $account = $data->getAccount();

        if ($account === null || $data->getCurrency() === null) {
            throw new AccountCurrencyMismatchException('Укажите счёт и валюту оплаты.');
        }

        if ($account->getCurrency() !== $data->getCurrency()) {
            throw new AccountCurrencyMismatchException(sprintf(
                'Счёт «%s» ведётся в %s — оплатить с него %s нельзя.',
                $account->getName(),
                $account->getCurrency()->value,
                $data->getCurrency()->value
            ));
        }
    }
}
