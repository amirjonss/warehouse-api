<?php

declare(strict_types=1);

namespace App\Component\Sale;

use App\Component\Core\Enums\DocStatus;
use App\Entity\Client;
use App\Entity\Sale;
use App\Entity\User;
use App\Repository\SaleRepository;
use DateTime;

class SaleFactory
{
    private const NUMBER_PREFIX = 'SL-';
    private const NUMBER_LENGTH = 5;

    public function __construct(private readonly SaleRepository $saleRepository)
    {
    }

    public function create(User $soldBy, Client $customer, string $note = '', DateTime $docDate = null): Sale
    {
        $sale = new Sale();
        $sale
            ->setNumber($this->generateNumber())
            ->setDocDate($docDate ?? new DateTime())
            ->setCustomer($customer)
            ->setNote($note)
            ->setSoldBy($soldBy)
            ->setCreatedAt(new DateTime())
            ->setTotalUsd('0')
            ->setTotalUzs('0')
            ->setStatus(DocStatus::DRAFT);

        return $sale;
    }

    private function generateNumber(): string
    {
        $sequence = 1;
        $lastNumber = $this->saleRepository->findLastNumber();
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
