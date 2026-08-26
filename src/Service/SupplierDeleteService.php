<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Supplier\Exceptions\SupplierInUseException;
use App\Entity\Supplier;
use App\Repository\BatchRepository;
use App\Repository\ReceiptRepository;
use Doctrine\ORM\EntityManagerInterface;

class SupplierDeleteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptRepository $receiptRepository,
        private BatchRepository $batchRepository,
    ) {
    }

    public function delete(Supplier $supplier): void
    {
        // receipts.supplier_id and batches.supplier_id are NOT NULL with a restricting
        // foreign key, so a supplier that has ever delivered anything cannot be removed.
        $blockers = [
            'receipt(s)' => $this->receiptRepository->count(['supplier' => $supplier]),
            'batch(es)' => $this->batchRepository->count(['supplier' => $supplier]),
        ];

        $used = array_filter($blockers);
        if ($used === []) {
            $this->entityManager->remove($supplier);
            $this->entityManager->flush();

            return;
        }

        $parts = [];
        foreach ($used as $label => $count) {
            $parts[] = $count . ' ' . $label;
        }

        throw new SupplierInUseException(sprintf(
            'Supplier "%s" still has %s and cannot be deleted.',
            $supplier->getName(),
            implode(', ', $parts)
        ));
    }
}
