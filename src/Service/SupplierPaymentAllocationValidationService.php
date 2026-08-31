<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentNotEditableException;
use App\Component\SupplierPaymentAllocation\Exceptions\MissingPayRateException;
use App\Component\SupplierPaymentAllocation\Exceptions\MissingReceiptException;
use App\Component\SupplierPaymentAllocation\Exceptions\ReceiptNotPostedException;
use App\Component\SupplierPaymentAllocation\Exceptions\SupplierMismatchException;
use App\Entity\SupplierPaymentAllocation;

/** Mirror of PaymentAllocationValidationService, with the receipt in the sale's place. */
class SupplierPaymentAllocationValidationService
{
    public function validate(SupplierPaymentAllocation $data): void
    {
        if ($data->getSupplierPayment()->getStatus() !== DocStatus::DRAFT) {
            throw new SupplierPaymentNotEditableException(
                'Cannot add allocations to a supplier payment that is not in draft status.'
            );
        }

        if ($data->getReceipt() === null) {
            throw new MissingReceiptException('A supplier payment allocation must reference a receipt.');
        }

        if ($data->getReceipt()->getStatus() !== DocStatus::POSTED) {
            throw new ReceiptNotPostedException('Cannot allocate a supplier payment to a receipt that is not posted yet.');
        }

        if ($data->getReceipt()->getSupplier()?->getId() !== $data->getSupplierPayment()->getSupplier()?->getId()) {
            throw new SupplierMismatchException('Cannot allocate a supplier payment to a receipt of a different supplier.');
        }

        if ($data->getCurrency() !== $data->getSupplierPayment()->getCurrency() && $data->getPayRate() === null) {
            throw new MissingPayRateException(
                'payRate is required when the allocation currency differs from the payment currency.'
            );
        }
    }
}
