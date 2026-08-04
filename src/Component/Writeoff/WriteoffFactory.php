<?php

namespace App\Component\Writeoff;

use App\Component\Core\Enums\DocStatus;
use App\Entity\User;
use App\Entity\Writeoff;
use App\Repository\WriteoffRepository;
use DateTime;

class WriteoffFactory
{
    private const NUMBER_PREFIX = 'СП-';
    private const NUMBER_LENGTH = 5;

    public function __construct(private readonly WriteoffRepository $writeoffRepository)
    {
    }

    public function create(User $createdBy, string $reason, DateTime $docDate = null): Writeoff
    {
        $writeoff = new Writeoff();
        $writeoff
            ->setNumber($this->generateNumber())
            ->setDocDate($docDate ?? new DateTime())
            ->setReason($reason)
            ->setCreatedBy($createdBy)
            ->setCreatedAt(new DateTime())
            ->setStatus(DocStatus::DRAFT);

        return $writeoff;
    }

    private function generateNumber(): string
    {
        $sequence = 1;
        $lastNumber = $this->writeoffRepository->findLastNumber();
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
