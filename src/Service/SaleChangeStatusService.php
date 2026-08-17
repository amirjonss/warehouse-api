<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Component\Core\Enums\ProfitEntryType;
use App\Component\Debt\DebtFactory;
use App\Component\Product\Enums\Currency;
use App\Component\Profit\ProfitFactory;
use App\Component\Sale\Exceptions\SaleStatusTransitionException;
use App\Component\SaleItem\SaleItemProfitCalculator;
use App\Component\StockMovement\StockMovementFactory;
use App\Component\User\CurrentUser;
use App\Entity\Sale;
use App\Repository\PaymentAllocationRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;

class SaleChangeStatusService
{
    public function __construct(
        private SaleRepository $saleRepository,
        private SaleItemAllocationService $saleItemAllocationService,
        private PaymentAllocationRepository $paymentAllocationRepository,
        private StockMovementFactory $stockMovementFactory,
        private SaleItemProfitCalculator $saleItemProfitCalculator,
        private ProfitFactory $profitFactory,
        private DebtFactory $debtFactory,
        private CurrentUser $currentUser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeStatus(Sale $sale): Sale
    {
        $previousStatus = $this->getPreviousStatus($sale);
        $newStatus = $sale->getStatus();

        if ($previousStatus === $newStatus) {
            return $sale;
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::DRAFT) {
            throw new SaleStatusTransitionException('A posted sale cannot be moved back to draft.');
        }

        if ($newStatus === DocStatus::POSTED) {
            return $this->post($sale, $previousStatus);
        }

        if ($previousStatus === DocStatus::POSTED && $newStatus === DocStatus::CANCELLED) {
            return $this->cancel($sale, $previousStatus);
        }

        $this->entityManager->flush();

        return $sale;
    }

    private function post(Sale $sale, ?DocStatus $previousStatus): Sale
    {
        if (count($sale->getItems()) === 0) {
            throw new SaleStatusTransitionException('Cannot post a sale without items.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($sale, $previousStatus) {
            $this->saleRepository->lockSales([$sale]);
            $this->assertNotChangedConcurrently($sale, $previousStatus);

            foreach ($sale->getItems() as $saleItem) {
                $this->saleItemAllocationService->allocate($saleItem);
            }

            $this->recordOutMovements($sale);
            $this->recordProfitEntries($sale);
            $this->recordDebtEntries($sale);

            return $sale;
        });
    }

    private function cancel(Sale $sale, ?DocStatus $previousStatus): Sale
    {
        return $this->entityManager->wrapInTransaction(function () use ($sale, $previousStatus) {
            $this->saleRepository->lockSales([$sale]);
            $this->assertNotChangedConcurrently($sale, $previousStatus);
            $this->assertNoActivePayments($sale);

            $this->reverseMovements($sale);
            $this->reverseProfitEntries($sale);
            $this->reverseDebtEntries($sale);

            return $sale;
        });
    }

    private function assertNotChangedConcurrently(Sale $sale, ?DocStatus $expectedStatus): void
    {
        if ($expectedStatus !== null && $this->saleRepository->getCurrentStatus($sale->getId()) !== $expectedStatus->value) {
            throw new SaleStatusTransitionException(
                'This sale was already changed by another request. Reload it and try again.'
            );
        }
    }

    private function assertNoActivePayments(Sale $sale): void
    {
        if ($this->paymentAllocationRepository->hasPostedAllocationForSale($sale)) {
            throw new SaleStatusTransitionException(
                'Cannot cancel a sale that has posted payments allocated to it. Cancel the related payment(s) first.'
            );
        }
    }

    private function recordOutMovements(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::OUT,
                    $saleItem->getProduct(),
                    $allocation->getBatch(),
                    bcmul($allocation->getQuantity(), '-1', 3),
                    DocumentType::SALE,
                    $sale->getId(),
                    $sale->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);
            }
        }
    }

    private function reverseMovements(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $stockMovement = $this->stockMovementFactory->create(
                    MovementType::ADJUST,
                    $saleItem->getProduct(),
                    $allocation->getBatch(),
                    $allocation->getQuantity(),
                    DocumentType::SALE,
                    $sale->getId(),
                    $sale->getNumber(),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($stockMovement);
            }
        }
    }

    private function recordProfitEntries(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $profit = $this->saleItemProfitCalculator->calculate($allocation);

                $profitEntry = $this->profitFactory->create(
                    ProfitEntryType::REALIZED,
                    $sale,
                    $saleItem,
                    $allocation,
                    $profit,
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($profitEntry);
            }
        }
    }

    private function reverseProfitEntries(Sale $sale): void
    {
        foreach ($sale->getItems() as $saleItem) {
            foreach ($saleItem->getAllocations() as $allocation) {
                $profit = $this->saleItemProfitCalculator->calculate($allocation);

                $profitEntry = $this->profitFactory->create(
                    ProfitEntryType::REVERSED,
                    $sale,
                    $saleItem,
                    $allocation,
                    bcmul($profit, '-1', 2),
                    $this->currentUser->getUser()
                );
                $this->entityManager->persist($profitEntry);
            }
        }
    }

    private function recordDebtEntries(Sale $sale): void
    {
        if (bccomp($sale->getTotalUsd(), '0', 2) > 0) {
            $debt = $this->debtFactory->create(
                DocumentType::SALE,
                $sale->getCustomer(),
                $sale,
                null,
                $sale->getTotalUsd(),
                Currency::USD,
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }

        if (bccomp($sale->getTotalUzs(), '0', 2) > 0) {
            $debt = $this->debtFactory->create(
                DocumentType::SALE,
                $sale->getCustomer(),
                $sale,
                null,
                $sale->getTotalUzs(),
                Currency::UZS,
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }
    }

    private function reverseDebtEntries(Sale $sale): void
    {
        if (bccomp($sale->getTotalUsd(), '0', 2) > 0) {
            $debt = $this->debtFactory->create(
                DocumentType::SALE,
                $sale->getCustomer(),
                $sale,
                null,
                bcmul($sale->getTotalUsd(), '-1', 2),
                Currency::USD,
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }

        if (bccomp($sale->getTotalUzs(), '0', 2) > 0) {
            $debt = $this->debtFactory->create(
                DocumentType::SALE,
                $sale->getCustomer(),
                $sale,
                null,
                bcmul($sale->getTotalUzs(), '-1', 2),
                Currency::UZS,
                $this->currentUser->getUser()
            );
            $this->entityManager->persist($debt);
        }
    }

    private function getPreviousStatus(Sale $sale): ?DocStatus
    {
        $status = $this->entityManager->getUnitOfWork()->getOriginalEntityData($sale)['status'] ?? null;

        return $status instanceof DocStatus ? $status : ($status !== null ? DocStatus::from($status) : null);
    }
}
