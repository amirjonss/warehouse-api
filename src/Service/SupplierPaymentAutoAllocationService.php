<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SupplierPayment\Exceptions\InsufficientSupplierPaymentAmountException;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentNotEditableException;
use App\Component\SupplierPaymentAllocation\SupplierPaymentAllocationCalculator;
use App\Entity\SupplierPayment;
use App\Entity\SupplierPaymentAllocation;
use App\Repository\SupplierDebtRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Mirror of PaymentAutoAllocationService, walking receipts instead of sales. */
class SupplierPaymentAutoAllocationService
{
    public function __construct(
        private SupplierDebtRepository $supplierDebtRepository,
        private SupplierPaymentAllocationCalculator $supplierPaymentAllocationCalculator,
        private SupplierPaymentChangeStatusService $supplierPaymentChangeStatusService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Spreads the payment across the supplier's unpaid receipts, oldest first and within
     * the payment's own currency, then posts it right away.
     */
    public function autoAllocateAndPost(SupplierPayment $payment): SupplierPayment
    {
        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new SupplierPaymentNotEditableException(
                'Автораспределение доступно только для оплаты в статусе черновика.'
            );
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment) {
            $this->clearExistingAllocations($payment);
            $this->buildAllocations($payment);

            $payment->setStatus(DocStatus::POSTED);

            return $this->supplierPaymentChangeStatusService->changeStatus($payment);
        });
    }

    private function clearExistingAllocations(SupplierPayment $payment): void
    {
        foreach ($payment->getAllocations()->toArray() as $allocation) {
            $this->entityManager->remove($allocation);
            $payment->removeAllocation($allocation);
        }

        $this->entityManager->flush();
    }

    private function buildAllocations(SupplierPayment $payment): void
    {
        $currency = $payment->getCurrency();
        $outstandingReceipts = $this->supplierDebtRepository
            ->getOutstandingReceiptsForSupplier($payment->getSupplier(), $currency);

        $totalOutstanding = '0';
        foreach ($outstandingReceipts as $row) {
            $totalOutstanding = bcadd($totalOutstanding, $row['balance'], 2);
        }

        if (bccomp($totalOutstanding, '0', 2) <= 0) {
            throw new InsufficientSupplierPaymentAmountException(sprintf(
                'У поставщика нет неоплаченных приходов в валюте %s.',
                $currency->value
            ));
        }

        if (bccomp($payment->getAmount(), $totalOutstanding, 2) > 0) {
            throw new InsufficientSupplierPaymentAmountException(sprintf(
                'Сумма оплаты %s %s превышает долг поставщику в этой валюте (%s) на %s.',
                $payment->getAmount(),
                $currency->value,
                $totalOutstanding,
                bcsub($payment->getAmount(), $totalOutstanding, 2)
            ));
        }

        $remaining = $payment->getAmount();

        foreach ($outstandingReceipts as $row) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $amountToClose = bccomp($row['balance'], $remaining, 2) < 0 ? $row['balance'] : $remaining;

            $allocation = new SupplierPaymentAllocation();
            $allocation
                ->setSupplierPayment($payment)
                ->setReceipt($row['receipt'])
                ->setCurrency($currency)
                ->setAmountSpent($amountToClose)
                ->setPayRate(null)
                ->setRoundingWriteOff('0.00');
            $allocation->setAmountClosed(
                $this->supplierPaymentAllocationCalculator->calculateAmountClosed($allocation)
            );

            $payment->addAllocation($allocation);
            $this->entityManager->persist($allocation);

            $remaining = bcsub($remaining, $amountToClose, 2);
        }
    }
}
