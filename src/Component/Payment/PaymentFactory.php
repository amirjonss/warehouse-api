<?php

declare(strict_types=1);

namespace App\Component\Payment;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Core\Enums\RateKind;
use App\Component\Product\Enums\Currency;
use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use DateTime;

class PaymentFactory
{
    private const NUMBER_PREFIX = 'PY-';
    private const NUMBER_LENGTH = 5;

    public function __construct(private readonly PaymentRepository $paymentRepository)
    {
    }

    public function create(
        User $acceptedBy,
        Client $client,
        string $amount,
        Currency $currency,
        PaymentMethod $method,
        ?RateKind $rateKind,
        ?string $rate,
        string $note = '',
        DateTime $docDate = null
    ): Payment {
        $payment = new Payment();
        $payment
            ->setNumber($this->generateNumber())
            ->setDocDate($docDate ?? new DateTime())
            ->setClient($client)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setRateKind($rateKind)
            ->setRate($rate)
            ->setMethod($method)
            ->setAcceptedBy($acceptedBy)
            ->setCreatedAt(new DateTime())
            ->setNote($note)
            ->setStatus(DocStatus::DRAFT);

        return $payment;
    }

    private function generateNumber(): string
    {
        $sequence = 1;
        $lastNumber = $this->paymentRepository->findLastNumber();
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return self::NUMBER_PREFIX . str_pad((string) $sequence, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }
}
