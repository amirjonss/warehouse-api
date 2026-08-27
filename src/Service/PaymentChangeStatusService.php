<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Debt\DebtFactory;
use App\Component\Payment\Exceptions\InsufficientPaymentAmountException;
use App\Component\Payment\Exceptions\PaymentStatusTransitionException;
use App\Component\User\CurrentUser;
use App\Entity\Payment;
use App\Repository\DebtRepository;
use App\Repository\PaymentRepository;
use App\Repository\SaleRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class PaymentChangeStatusService
{
    public function __construct(
        private DebtRepository $debtRepository,
        private DebtFactory $debtFactory,
        private SaleRepository $saleRepository,
        private PaymentRepository $paymentRepository,
        private CashCollectService $cashCollectService,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Payment $payment): Payment
    {
        $previousStatus = $this->getPreviousStatus($payment);
        $newStatus = $payment->getStatus();

        if ($previousStatus === $newStatus) {
            return $payment;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new PaymentStatusTransitionException('A posted payment cannot be moved back to draft.');
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($payment, $previousStatus);
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($payment, $previousStatus);
        }

        $this->entityManager->flush();

        return $payment;
    }

    private function post(Payment $payment, ?DocStatus $previousStatus): Payment
    {
        $this->assertHasAllocations($payment);
        $this->assertFullyAllocated($payment);

        return $this->entityManager->wrapInTransaction(function () use ($payment, $previousStatus) {
            $this->paymentRepository->lockPayments([$payment]);
            $this->assertNotChangedConcurrently($payment, $previousStatus);

            $this->saleRepository->lockSales($this->collectSales($payment));

            $this->assertNotExceedingDebt($payment);
            $this->recordDebtEntries($payment);
            $payment->setPostedAt(new DateTime());
            // Долг клиента закрылся — теперь фиксируем, у кого оказались деньги.
            $this->cashCollectService->record($payment);

            return $payment;
        });
    }

    private function cancel(Payment $payment, ?DocStatus $previousStatus): Payment
    {
        return $this->entityManager->wrapInTransaction(function () use ($payment, $previousStatus) {
            $this->paymentRepository->lockPayments([$payment]);
            $this->assertNotChangedConcurrently($payment, $previousStatus);

            $this->reverseDebtEntries($payment);
            $this->cashCollectService->reverse($payment);

            return $payment;
        });
    }

    public function cancelPosted(Payment $payment): void
    {
        $this->paymentRepository->lockPayments([$payment]);

        if ($this->paymentRepository->getCurrentStatus($payment->getId()) !== DocStatus::POSTED->value) {
            return;
        }

        $this->reverseDebtEntries($payment);
        $this->cashCollectService->reverse($payment);
        $payment->setStatus(DocStatus::CANCELLED);
    }

    private function assertNotChangedConcurrently(Payment $payment, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null && $this->paymentRepository->getCurrentStatus($payment->getId()) !== $expectedStatus->value) {
            throw new PaymentStatusTransitionException(
                'This payment was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function collectSales(Payment $payment): array
    {
        $sales = [];
        foreach ($payment->getAllocations() as $allocation) {
            $sales[] = $allocation->getSale();
        }

        return $sales;
    }

    private function assertHasAllocations(Payment $payment): void
    {
        if (count($payment->getAllocations()) === 0) {
            throw new PaymentStatusTransitionException('Cannot post a payment without allocations.');
        }
    }

    private function assertFullyAllocated(Payment $payment): void
    {
        $totalSpent = '0';

        foreach ($payment->getAllocations() as $allocation) {
            $totalSpent = bcadd($totalSpent, $allocation->getAmountSpent(), 2);
        }

        if (bccomp($totalSpent, $payment->getAmount(), 2) > 0) {
            throw new InsufficientPaymentAmountException(sprintf(
                'Allocated amount %s exceeds the payment amount %s.',
                $totalSpent,
                $payment->getAmount()
            ));
        }

        if (bccomp($totalSpent, $payment->getAmount(), 2) < 0) {
            throw new InsufficientPaymentAmountException(sprintf(
                'Allocated amount %s is less than the payment amount %s — allocate the remaining %s before posting.',
                $totalSpent,
                $payment->getAmount(),
                bcsub($payment->getAmount(), $totalSpent, 2)
            ));
        }
    }

    private function assertNotExceedingDebt(Payment $payment): void
    {
        $requestedBySaleCurrency = [];
        $salesById = [];

        foreach ($payment->getAllocations() as $allocation) {
            $sale = $allocation->getSale();
            $key = $sale->getId() . '|' . $allocation->getCurrency()->value;

            $salesById[$key] = $sale;
            $requestedBySaleCurrency[$key] = bcadd(
                $requestedBySaleCurrency[$key] ?? '0',
                $allocation->getAmountClosed(),
                2
            );
        }

        foreach ($requestedBySaleCurrency as $key => $requestedClosed) {
            $sale = $salesById[$key];
            [, $currencyValue] = explode('|', $key);

            $outstanding = $this->debtRepository->getBalanceForSale($sale)[$currencyValue] ?? '0';

            if (bccomp($requestedClosed, $outstanding, 2) > 0) {
                throw new InsufficientPaymentAmountException(sprintf(
                    'Cannot close %s %s of sale "%s": only %s left.',
                    $requestedClosed,
                    $currencyValue,
                    $sale->getNumber(),
                    $outstanding
                ));
            }
        }
    }

    private function recordDebtEntries(Payment $payment): void
    {
        foreach ($payment->getAllocations() as $allocation) {
            $sale = $allocation->getSale();

            $debt = $this->debtFactory->create(
                DocumentType::PAYMENT,
                $sale->getCustomer(),
                $sale,
                $payment,
                bcmul($allocation->getAmountClosed(), '-1', 2),
                $allocation->getCurrency(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }
    }

    private function reverseDebtEntries(Payment $payment): void
    {
        foreach ($payment->getAllocations() as $allocation) {
            $sale = $allocation->getSale();

            $debt = $this->debtFactory->create(
                DocumentType::PAYMENT,
                $sale->getCustomer(),
                $sale,
                $payment,
                $allocation->getAmountClosed(),
                $allocation->getCurrency(),
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }
    }

    private function getPreviousStatus(Payment $payment): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($payment)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
