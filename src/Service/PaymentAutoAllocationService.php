<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Payment\Exceptions\InsufficientPaymentAmountException;
use App\Component\PaymentAllocation\Exceptions\PaymentNotEditableException;
use App\Component\PaymentAllocation\PaymentAllocationCalculator;
use App\Entity\Payment;
use App\Entity\PaymentAllocation;
use App\Repository\DebtRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentAutoAllocationService
{
    public function __construct(
        private DebtRepository $debtRepository,
        private PaymentAllocationCalculator $paymentAllocationCalculator,
        private PaymentChangeStatusService $paymentChangeStatusService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Spreads the payment across the client's unsettled sales, oldest first and within
     * the payment's own currency, then posts it right away.
     */
    public function autoAllocateAndPost(Payment $payment): Payment
    {
        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new PaymentNotEditableException('Автораспределение доступно только для платежа в статусе черновика.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment) {
            $this->clearExistingAllocations($payment);
            $this->buildAllocations($payment);

            $payment->setStatus(DocStatus::POSTED);

            return $this->paymentChangeStatusService->changeStatus($payment);
        });
    }

    private function clearExistingAllocations(Payment $payment): void
    {
        foreach ($payment->getAllocations()->toArray() as $allocation) {
            $this->entityManager->remove($allocation);
            $payment->removeAllocation($allocation);
        }

        $this->entityManager->flush();
    }

    private function buildAllocations(Payment $payment): void
    {
        $currency = $payment->getCurrency();
        $outstandingSales = $this->debtRepository->getOutstandingSalesForClient($payment->getClient(), $currency);

        $totalOutstanding = '0';
        foreach ($outstandingSales as $row) {
            $totalOutstanding = bcadd($totalOutstanding, $row['balance'], 2);
        }

        if (bccomp($totalOutstanding, '0', 2) <= 0) {
            throw new InsufficientPaymentAmountException(sprintf(
                'У клиента нет непогашенного долга в валюте %s.',
                $currency->value
            ));
        }

        if (bccomp($payment->getAmount(), $totalOutstanding, 2) > 0) {
            throw new InsufficientPaymentAmountException(sprintf(
                'Сумма платежа %s %s превышает долг клиента в этой валюте (%s) на %s.',
                $payment->getAmount(),
                $currency->value,
                $totalOutstanding,
                bcsub($payment->getAmount(), $totalOutstanding, 2)
            ));
        }

        $remaining = $payment->getAmount();

        foreach ($outstandingSales as $row) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $amountToClose = bccomp($row['balance'], $remaining, 2) < 0 ? $row['balance'] : $remaining;

            $allocation = new PaymentAllocation();
            $allocation
                ->setPayment($payment)
                ->setSale($row['sale'])
                ->setCurrency($currency)
                ->setAmountSpent($amountToClose)
                ->setPayRate(null)
                ->setIsRounding(false);
            $allocation->setAmountClosed($this->paymentAllocationCalculator->calculateAmountClosed($allocation));

            $payment->addAllocation($allocation);
            $this->entityManager->persist($allocation);

            $remaining = bcsub($remaining, $amountToClose, 2);
        }
    }
}
