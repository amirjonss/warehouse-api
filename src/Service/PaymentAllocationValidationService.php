<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\PaymentAllocation\Exceptions\MissingSaleException;
use App\Component\PaymentAllocation\Exceptions\PaymentClientMismatchException;
use App\Component\PaymentAllocation\Exceptions\PaymentNotEditableException;
use App\Component\PaymentAllocation\Exceptions\SaleNotPostedException;
use App\Entity\PaymentAllocation;

class PaymentAllocationValidationService
{
    public function validate(PaymentAllocation $data): void
    {
        if ($data->getPayment()->getStatus() !== DocStatus::DRAFT) {
            throw new PaymentNotEditableException('Cannot add allocations to a payment that is not in draft status.');
        }

        if ($data->getSale() === null) {
            throw new MissingSaleException('A payment allocation must reference a sale.');
        }

        if ($data->getSale()->getStatus() !== DocStatus::POSTED) {
            throw new SaleNotPostedException('Cannot allocate a payment to a sale that is not posted yet.');
        }

        if ($data->getSale()->getCustomer()?->getId() !== $data->getPayment()->getClient()?->getId()) {
            throw new PaymentClientMismatchException('Cannot allocate a payment to a sale of a different client.');
        }
    }
}
