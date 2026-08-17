<?php

declare(strict_types=1);

namespace App\Component\Batch;

use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\ReceiptItem;
use App\Repository\BatchRepository;

class BatchFactory
{
    private const NUMBER_PREFIX = 'B-';
    private const NUMBER_LENGTH = 4;

    public function __construct(private readonly BatchRepository $batchRepository)
    {
    }

    public function create(ReceiptItem $data): Batch
    {
        $receipt = $data->getReceipt();

        $batch = new Batch();
        $batch
            ->setNumber($this->generateNumber($data->getProduct()))
            ->setProduct($data->getProduct())
            ->setReceivedAt($receipt->getDocDate())
            ->setInitialQty($data->getQuantity())
            ->setPurchasePrice($data->getPrice())
            ->setCurrency($data->getCurrency())
            ->setRateSell($data->getRate())
            ->setSupplier($receipt->getSupplier())
            ->setReceipt($receipt);

        return $batch;
    }

    private function generateNumber(Product $product): string
    {
        $sequence = 1;
        $lastNumber = $this->batchRepository->findLastNumberForProduct($product);
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
