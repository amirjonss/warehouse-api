<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Sale\Exceptions\InsufficientBatchQuantityException;
use App\Component\Sale\SaleTotalsCalculator;
use App\Component\SaleItem\Exceptions\MissingSaleRateException;
use App\Component\SaleItem\SaleItemFactory;
use App\Entity\SaleItem;
use App\Entity\SaleItemAllocation;
use App\Repository\BatchRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class SaleItemAllocationService
{
    public function __construct(
        private BatchRepository $batchRepository,
        private ProductRepository $productRepository,
        private SaleItemFactory $saleItemFactory,
        private SaleTotalsCalculator $saleTotalsCalculator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createWithAllocation(SaleItem $data): SaleItem
    {
        return $this->entityManager->wrapInTransaction(function () use ($data) {
            $saleItem = $this->saleItemFactory->create($data);
            $data->getSale()->addItem($saleItem);
            $this->entityManager->persist($saleItem);

            $this->allocate($saleItem);

            $this->saleTotalsCalculator->recalculate($data->getSale());

            return $saleItem;
        });
    }

    public function allocate(SaleItem $saleItem): void
    {
        foreach ($saleItem->getAllocations()->toArray() as $existingAllocation) {
            $this->entityManager->remove($existingAllocation);
            $saleItem->removeAllocation($existingAllocation);
        }

        $this->entityManager->flush();

        $remainingToAllocate = $saleItem->getQuantity();

        $batches = $this->batchRepository->findBy(
            ['product' => $saleItem->getProduct()],
            ['receivedAt' => 'ASC', 'id' => 'ASC']
        );

        $this->productRepository->lockProducts([$saleItem->getProduct()]);
        $this->batchRepository->lockBatches($batches);

        foreach ($batches as $batch) {
            if (bccomp($remainingToAllocate, '0', 3) <= 0) {
                break;
            }

            $availableQty = $this->batchRepository->computeLiveRemainingQty($batch);
            if (bccomp($availableQty, '0', 3) <= 0) {
                continue;
            }

            $allocatedQty = bccomp($availableQty, $remainingToAllocate, 3) < 0
                ? $availableQty
                : $remainingToAllocate;

            $allocation = new SaleItemAllocation();
            $allocation
                ->setBatch($batch)
                ->setQuantity($allocatedQty)
                ->setCostPrice($batch->getPurchasePrice())
                ->setCostCurrency($batch->getCurrency())
                ->setCostRate($batch->getRateSell());

            $saleItem->addAllocation($allocation);
            $this->entityManager->persist($allocation);

            $remainingToAllocate = bcsub($remainingToAllocate, $allocatedQty, 3);
        }

        if (bccomp($remainingToAllocate, '0', 3) > 0) {
            throw new InsufficientBatchQuantityException(sprintf(
                'Not enough stock of product "%s": short by %s.',
                $saleItem->getProduct()->getName(),
                $remainingToAllocate
            ));
        }

        $this->assertRateProvidedForCrossCurrency($saleItem);
    }

    private function assertRateProvidedForCrossCurrency(SaleItem $saleItem): void
    {
        if ($saleItem->getRate() !== null) {
            return;
        }

        foreach ($saleItem->getAllocations() as $allocation) {
            if ($saleItem->getCurrency() !== $allocation->getCostCurrency()) {
                throw new MissingSaleRateException(sprintf(
                    'Товар «%s» продан в валюте %s, а закуплен в %s — укажите курс в позиции продажи.',
                    $saleItem->getProduct()->getName(),
                    $saleItem->getCurrency()->value,
                    $allocation->getCostCurrency()->value
                ));
            }
        }
    }
}
