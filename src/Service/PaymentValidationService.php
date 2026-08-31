<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\CashAccountResolver;
use App\Entity\Payment;

/**
 * Shape rules that must hold before a payment document is even created. Business rules
 * live in services rather than entity constraints: API Platform validates on
 * kernel.view, which is after a custom controller has already run.
 */
class PaymentValidationService
{
    public function __construct(private readonly CashAccountResolver $cashAccountResolver)
    {
    }

    public function validate(Payment $data): void
    {
        $this->cashAccountResolver->assertRoutable($data);
    }
}
