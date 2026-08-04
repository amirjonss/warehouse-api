<?php

namespace App\Component\Receipt;

use App\Component\Core\Enums\DocStatus;
use App\Entity\Receipt;
use App\Entity\Supplier;
use App\Entity\User;
use App\Repository\ReceiptRepository;
use DateTime;

class ReceiptFactory
{
    private const NUMBER_PREFIX = 'ПР-';
    private const NUMBER_LENGTH = 5;

    public function __construct(private readonly ReceiptRepository $receiptRepository)
    {
    }

    public function create(User $receivedBy, Supplier $supplier, string $note = "", DateTime $docDate = null): Receipt
    {
        $receipt = new Receipt();
        $receipt
            ->setNote($note)
            ->setNumber($this->generateNumber())
            ->setReceivedBy($receivedBy)
            ->setSupplier($supplier)
            ->setDocDate($docDate ?? new DateTime())
            ->setCreatedAt(new DateTime())
            ->setTotalUsd(0)
            ->setTotalUzs(0)
            ->setStatus(DocStatus::DRAFT);

        return $receipt;
    }

    private function generateNumber(): string
    {
        $sequence = 1;
        $lastNumber = $this->receiptRepository->findLastNumber();
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
