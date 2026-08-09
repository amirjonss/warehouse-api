<?php

namespace App\Service;

use App\Component\Sale\Exceptions\InsufficientBatchQuantityException;
use App\Component\Sale\SaleTotalsCalculator;
use App\Component\SaleItem\SaleItemFactory;
use App\Entity\SaleItem;
use App\Entity\SaleItemAllocation;
use App\Repository\BatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class SaleItemAllocationService
{
    public function __construct(
        private BatchRepository $batchRepository,
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

        // Doctrine executes inserts before deletes within a single flush, so without this
        // the re-allocation below could try to insert the same (sale_item_id, batch_id) pair
        // the old allocation still occupies and hit the unique constraint.
        $this->entityManager->flush();

        $remainingToAllocate = $saleItem->getQuantity();

        $batches = $this->batchRepository->findBy(
            ['product' => $saleItem->getProduct()],
            ['receivedAt' => 'ASC', 'id' => 'ASC']
        );

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
    }
}
