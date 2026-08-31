<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Account\AccountEntryFactory;
use App\Component\Account\Enums\AccountEntryKind;
use App\Component\Account\Exceptions\InsufficientAccountBalanceException;
use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Product\Enums\Currency;
use App\Component\SupplierDebt\SupplierDebtFactory;
use App\Component\SupplierPayment\Exceptions\InsufficientSupplierPaymentAmountException;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentStatusTransitionException;
use App\Component\SupplierPaymentAllocation\RoundingPolicy;
use App\Component\User\CurrentUser;
use App\Entity\CashAccount;
use App\Entity\SupplierPayment;
use App\Repository\CashAccountRepository;
use App\Repository\ReceiptRepository;
use App\Repository\SupplierDebtRepository;
use App\Repository\SupplierPaymentRepository;
use App\Repository\SupplierRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Posting a supplier payment closes the payables it was allocated to and takes the money
 * off the account it names. Structural copy of PaymentChangeStatusService; the two
 * differences worth knowing are the rounding pass and the fact that cancelling needs no
 * sufficiency check.
 */
class SupplierPaymentChangeStatusService
{
    public function __construct(
        private SupplierPaymentRepository $supplierPaymentRepository,
        private SupplierDebtRepository $supplierDebtRepository,
        private SupplierDebtFactory $supplierDebtFactory,
        private SupplierRepository $supplierRepository,
        private ReceiptRepository $receiptRepository,
        private CashAccountRepository $cashAccountRepository,
        private AccountEntryFactory $accountEntryFactory,
        private RoundingPolicy $roundingPolicy,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(SupplierPayment $payment): SupplierPayment
    {
        $previousStatus = $this->getPreviousStatus($payment);
        $newStatus = $payment->getStatus();

        if ($previousStatus === $newStatus) {
            return $payment;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new SupplierPaymentStatusTransitionException(
                'A posted supplier payment cannot be moved back to draft.'
            );
        }

        if ($previousStatus === DocStatus::CANCELLED) {
            throw new SupplierPaymentStatusTransitionException(
                'Отменённую оплату поставщику нельзя провести заново — создайте новый документ.'
            );
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

    private function post(SupplierPayment $payment, ?DocStatus $previousStatus): SupplierPayment
    {
        $this->assertHasAllocations($payment);
        $this->assertFullyAllocated($payment);

        return $this->entityManager->wrapInTransaction(function () use ($payment, $previousStatus) {
            $this->supplierPaymentRepository->lockSupplierPayments([$payment]);
            $this->assertNotChangedConcurrently($payment, $previousStatus);

            $this->receiptRepository->lockReceipts($this->collectReceipts($payment));
            $this->supplierRepository->lockSuppliers([$payment->getSupplier()]);
            $this->cashAccountRepository->lockAccounts([$payment->getAccount()]);

            $this->applyRoundingAndAssertNotExceedingPayable($payment);
            $this->assertEnoughBalance($payment->getAccount(), $payment->getAmount());

            $this->recordSupplierDebtEntries($payment, '-1');

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::SUPPLIER_PAYMENT,
                $payment->getAccount(),
                bcmul($payment->getAmount(), '-1', 2),
                null,
                null,
                null,
                sprintf('Оплата поставщику «%s»', $payment->getNumber()),
                $this->currentUser->getUser(),
                $payment
            ));

            $payment->setPostedAt(new DateTime());

            return $payment;
        });
    }

    private function cancel(SupplierPayment $payment, ?DocStatus $previousStatus): SupplierPayment
    {
        return $this->entityManager->wrapInTransaction(function () use ($payment, $previousStatus) {
            $this->supplierPaymentRepository->lockSupplierPayments([$payment]);
            $this->assertNotChangedConcurrently($payment, $previousStatus);

            $this->receiptRepository->lockReceipts($this->collectReceipts($payment));
            $this->supplierRepository->lockSuppliers([$payment->getSupplier()]);
            $this->cashAccountRepository->lockAccounts([$payment->getAccount()]);

            // No sufficiency check here, unlike the client-payment path: cancelling a
            // payout only ever raises the account balance and reopens the payable.
            $this->recordSupplierDebtEntries($payment, '1');

            $this->entityManager->persist($this->accountEntryFactory->create(
                AccountEntryKind::SUPPLIER_PAYMENT,
                $payment->getAccount(),
                $payment->getAmount(),
                null,
                null,
                null,
                sprintf('Сторно оплаты поставщику «%s»', $payment->getNumber()),
                $this->currentUser->getUser(),
                $payment
            ));

            return $payment;
        });
    }

    /**
     * The agreed sum rarely divides into the invoice currency exactly. Where the leftover
     * is smaller than the currency's grain it is an artefact of the division, not money,
     * and it is written off so the receipt lands on exactly zero.
     *
     * Done at posting time, not when the allocation is created: the live payable is only
     * known under the lock.
     */
    private function applyRoundingAndAssertNotExceedingPayable(SupplierPayment $payment): void
    {
        $groups = [];
        foreach ($payment->getAllocations() as $allocation) {
            $key = $allocation->getReceipt()->getId() . '|' . $allocation->getCurrency()->value;
            $groups[$key][] = $allocation;
        }

        foreach ($groups as $key => $allocations) {
            $receipt = $allocations[0]->getReceipt();
            $currency = $allocations[0]->getCurrency();

            $outstanding = $this->supplierDebtRepository->getBalanceForReceipt($receipt)[$currency->value] ?? '0';

            $requested = '0';
            foreach ($allocations as $allocation) {
                $allocation->setRoundingWriteOff('0.00');
                $requested = bcadd($requested, $allocation->getAmountClosed(), 2);
            }

            $writeOff = $this->roundingPolicy->resolve($outstanding, $requested, $currency);

            if (bccomp(bcadd($requested, $writeOff, 2), $outstanding, 2) > 0) {
                throw new InsufficientSupplierPaymentAmountException(sprintf(
                    'Нельзя закрыть %s %s по приходу «%s»: осталось только %s.',
                    $requested,
                    $currency->value,
                    $receipt->getNumber(),
                    $outstanding
                ));
            }

            if (bccomp($writeOff, '0', 2) !== 0) {
                // Deterministically on the last allocation of the group.
                $allocations[count($allocations) - 1]->setRoundingWriteOff($writeOff);
            }
        }
    }

    /**
     * One row per allocation, exactly as debts does — the kopeck rides along inside it
     * and is explained by the allocation's roundingWriteOff.
     */
    private function recordSupplierDebtEntries(SupplierPayment $payment, string $sign): void
    {
        foreach ($payment->getAllocations() as $allocation) {
            $this->entityManager->persist($this->supplierDebtFactory->create(
                DocumentType::SUPPLIER_PAYMENT,
                $payment->getSupplier(),
                $allocation->getReceipt(),
                $payment,
                bcmul($allocation->getTotalClosed(), $sign, 2),
                $allocation->getCurrency(),
                $this->currentUser->getUser()
            ));
        }
    }

    private function assertHasAllocations(SupplierPayment $payment): void
    {
        if (count($payment->getAllocations()) === 0) {
            throw new SupplierPaymentStatusTransitionException(
                'Cannot post a supplier payment without allocations.'
            );
        }
    }

    private function assertFullyAllocated(SupplierPayment $payment): void
    {
        $totalSpent = '0';
        foreach ($payment->getAllocations() as $allocation) {
            $totalSpent = bcadd($totalSpent, $allocation->getAmountSpent(), 2);
        }

        if (bccomp($totalSpent, $payment->getAmount(), 2) > 0) {
            throw new InsufficientSupplierPaymentAmountException(sprintf(
                'Разнесено %s, что больше суммы оплаты %s.',
                $totalSpent,
                $payment->getAmount()
            ));
        }

        if (bccomp($totalSpent, $payment->getAmount(), 2) < 0) {
            throw new InsufficientSupplierPaymentAmountException(sprintf(
                'Разнесено %s из %s — распределите оставшиеся %s перед проведением.',
                $totalSpent,
                $payment->getAmount(),
                bcsub($payment->getAmount(), $totalSpent, 2)
            ));
        }
    }

    private function assertEnoughBalance(CashAccount $account, string $amount): void
    {
        if (bccomp($account->getBalance() ?? '0', $amount, 2) >= 0) {
            return;
        }

        throw new InsufficientAccountBalanceException(sprintf(
            'На счёте «%s» осталось %s %s — оплатить %s %s нельзя.',
            $account->getName(),
            $account->getBalance(),
            $account->getCurrency()->value,
            $amount,
            $account->getCurrency()->value
        ));
    }

    /**
     * @return \App\Entity\Receipt[]
     */
    private function collectReceipts(SupplierPayment $payment): array
    {
        $receipts = [];
        foreach ($payment->getAllocations() as $allocation) {
            $receipts[] = $allocation->getReceipt();
        }

        return $receipts;
    }

    private function assertNotChangedConcurrently(SupplierPayment $payment, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null
            && $this->supplierPaymentRepository->getCurrentStatus($payment->getId()) !== $expectedStatus->value) {
            throw new SupplierPaymentStatusTransitionException(
                'This supplier payment was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function getPreviousStatus(SupplierPayment $payment): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($payment)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
