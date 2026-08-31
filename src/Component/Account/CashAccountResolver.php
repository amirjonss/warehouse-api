<?php

declare(strict_types=1);

namespace App\Component\Account;

use App\Component\Account\Enums\CashAccountKind;
use App\Component\Account\Exceptions\AccountNotFoundException;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Payment\Exceptions\UnsupportedPaymentMethodException;
use App\Component\Product\Enums\Currency;
use App\Entity\CashAccount;
use App\Entity\Payment;
use App\Repository\CashAccountRepository;

/**
 * Which account a given piece of money belongs in. The (kind, currency) pair is the
 * identity of an account, so routing is a lookup rather than a configured default.
 */
class CashAccountResolver
{
    public function __construct(private readonly CashAccountRepository $cashAccountRepository)
    {
    }

    public function forKind(CashAccountKind $kind, Currency $currency): CashAccount
    {
        $account = $this->cashAccountRepository->findByKindAndCurrency($kind, $currency);

        if ($account === null) {
            throw new AccountNotFoundException(sprintf(
                'Счёт «%s» в валюте %s не заведён.',
                $kind->value,
                $currency->value
            ));
        }

        return $account;
    }

    public function forPayment(Payment $payment): CashAccount
    {
        $this->assertRoutable($payment);

        return $this->forKind($this->kindFor($payment->getMethod()), $payment->getCurrency());
    }

    /**
     * The business has no currency bank account and no dollar card, so dollars can only
     * ever be taken as cash. Without this the debt would close and the money would land
     * nowhere.
     */
    public function assertRoutable(Payment $payment): void
    {
        if ($payment->getCurrency() !== Currency::USD) {
            return;
        }

        if ($payment->getMethod() === PaymentMethod::CASH) {
            return;
        }

        throw new UnsupportedPaymentMethodException(
            'Оплата в USD невозможна картой или переводом: валютного счёта нет, '
            . 'доллары принимаются только наличными.'
        );
    }

    private function kindFor(PaymentMethod $method): CashAccountKind
    {
        return match ($method) {
            PaymentMethod::CASH => CashAccountKind::CASH,
            PaymentMethod::CARD => CashAccountKind::CARD,
            PaymentMethod::TRANSFER => CashAccountKind::BANK,
        };
    }
}
