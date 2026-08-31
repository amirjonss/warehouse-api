<?php

declare(strict_types=1);

namespace App\Component\SupplierPayment;

use App\Component\Core\DocumentNumberGenerator;
use App\Component\Core\Enums\DocStatus;
use App\Component\Product\Enums\Currency;
use App\Entity\CashAccount;
use App\Entity\Supplier;
use App\Entity\SupplierPayment;
use App\Entity\User;
use App\Repository\SupplierPaymentRepository;
use DateTime;
use DateTimeInterface;

class SupplierPaymentFactory
{
    private const NUMBER_PREFIX = 'SP-';
    private const NUMBER_LENGTH = 5;

    public function __construct(
        private readonly SupplierPaymentRepository $supplierPaymentRepository,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {
    }

    public function create(
        User $paidBy,
        Supplier $supplier,
        CashAccount $account,
        string $amount,
        Currency $currency,
        ?string $note = null,
        ?DateTimeInterface $docDate = null
    ): SupplierPayment {
        $payment = new SupplierPayment();
        $payment
            ->setNumber($this->documentNumberGenerator->next(
                self::NUMBER_PREFIX,
                self::NUMBER_LENGTH,
                $this->supplierPaymentRepository->findLastNumber()
            ))
            ->setDocDate($docDate ?? new DateTime())
            ->setSupplier($supplier)
            ->setAccount($account)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setPaidBy($paidBy)
            ->setCreatedAt(new DateTime())
            ->setNote($note)
            ->setStatus(DocStatus::DRAFT);

        return $payment;
    }
}
