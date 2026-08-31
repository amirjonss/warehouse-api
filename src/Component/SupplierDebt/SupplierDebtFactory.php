<?php

declare(strict_types=1);

namespace App\Component\SupplierDebt;

use App\Component\Core\Enums\DocumentType;
use App\Component\Product\Enums\Currency;
use App\Entity\Receipt;
use App\Entity\Supplier;
use App\Entity\SupplierDebt;
use App\Entity\SupplierPayment;
use App\Entity\User;
use DateTime;

/**
 * Mirror of DebtFactory: writes the journal row and moves the supplier's denormalised
 * total in the same breath, so the two cannot drift apart.
 */
class SupplierDebtFactory
{
    public function create(
        DocumentType $docType,
        Supplier $supplier,
        Receipt $receipt,
        ?SupplierPayment $supplierPayment,
        string $amount,
        Currency $currency,
        User $createdBy
    ): SupplierDebt {
        $debt = new SupplierDebt();
        $debt
            ->setOccurredAt(new DateTime())
            ->setDocType($docType)
            ->setSupplier($supplier)
            ->setReceipt($receipt)
            ->setSupplierPayment($supplierPayment)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setCreatedBy($createdBy);

        if ($currency === Currency::USD) {
            $supplier->setDebtUsd(bcadd($supplier->getDebtUsd() ?? '0', $amount, 2));
        } else {
            $supplier->setDebtUzs(bcadd($supplier->getDebtUzs() ?? '0', $amount, 2));
        }

        return $debt;
    }
}
