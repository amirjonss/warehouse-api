<?php

declare(strict_types=1);

namespace App\Component\MoneyTransfer;

use App\Component\Core\DocumentNumberGenerator;
use App\Component\Core\Enums\DocStatus;
use App\Entity\CashAccount;
use App\Entity\MoneyTransfer;
use App\Entity\User;
use App\Repository\MoneyTransferRepository;
use DateTime;
use DateTimeInterface;

class MoneyTransferFactory
{
    private const NUMBER_PREFIX = 'MT-';
    private const NUMBER_LENGTH = 5;

    public function __construct(
        private readonly MoneyTransferRepository $moneyTransferRepository,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {
    }

    public function create(
        User $createdBy,
        CashAccount $fromAccount,
        CashAccount $toAccount,
        string $amountSent,
        string $amountReceived,
        ?string $rate,
        ?string $note = null,
        ?DateTimeInterface $docDate = null
    ): MoneyTransfer {
        $transfer = new MoneyTransfer();
        $transfer
            ->setNumber($this->documentNumberGenerator->next(
                self::NUMBER_PREFIX,
                self::NUMBER_LENGTH,
                $this->moneyTransferRepository->findLastNumber()
            ))
            ->setDocDate($docDate ?? new DateTime())
            ->setFromAccount($fromAccount)
            ->setToAccount($toAccount)
            ->setAmountSent($amountSent)
            ->setAmountReceived($amountReceived)
            ->setRate($rate)
            ->setCreatedAt(new DateTime())
            ->setCreatedBy($createdBy)
            ->setNote($note)
            ->setStatus(DocStatus::DRAFT);

        return $transfer;
    }
}
