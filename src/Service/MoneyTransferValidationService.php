<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\AmountClosedCalculator;
use App\Component\MoneyTransfer\Exceptions\MoneyTransferValidationException;
use App\Entity\MoneyTransfer;

/**
 * Both amounts are physically counted, so both are authoritative. Within one currency
 * they must match to the kopeck — the bank takes no commission on a deposit, so any
 * difference is a typo. Across currencies the agreed rate explains the gap, and it is
 * only used here to catch a mistyped digit.
 */
class MoneyTransferValidationService
{
    private const RATE_TOLERANCE = '0.01';

    public function __construct(private readonly AmountClosedCalculator $amountClosedCalculator)
    {
    }

    public function validate(MoneyTransfer $data): void
    {
        $from = $data->getFromAccount();
        $to = $data->getToAccount();

        if ($from === null || $to === null) {
            throw new MoneyTransferValidationException('Укажите счёт списания и счёт зачисления.');
        }

        if ($from->getId() === $to->getId()) {
            throw new MoneyTransferValidationException('Счёт списания и счёт зачисления должны отличаться.');
        }

        $this->assertPositive($data->getAmountSent(), 'списания');
        $this->assertPositive($data->getAmountReceived(), 'зачисления');

        if ($from->getCurrency() === $to->getCurrency()) {
            $this->assertSameCurrency($data);

            return;
        }

        $this->assertCrossCurrency($data);
    }

    private function assertPositive(?string $amount, string $label): void
    {
        if ($amount !== null && bccomp($amount, '0', 2) > 0) {
            return;
        }

        throw new MoneyTransferValidationException(sprintf('Сумма %s должна быть больше нуля.', $label));
    }

    private function assertSameCurrency(MoneyTransfer $data): void
    {
        if ($data->getRate() !== null) {
            throw new MoneyTransferValidationException(
                'Перевод внутри одной валюты не требует курса — уберите его.'
            );
        }

        if (bccomp($data->getAmountSent(), $data->getAmountReceived(), 2) !== 0) {
            throw new MoneyTransferValidationException(sprintf(
                'Перевод внутри одной валюты: списано %s, а зачислено %s — суммы должны совпадать.',
                $data->getAmountSent(),
                $data->getAmountReceived()
            ));
        }
    }

    private function assertCrossCurrency(MoneyTransfer $data): void
    {
        $rate = $data->getRate();

        if ($rate === null) {
            throw new MoneyTransferValidationException(
                'Обмен валюты: укажите курс, по которому он сделан.'
            );
        }

        $expected = $this->amountClosedCalculator->calculate(
            $data->getToAccount()->getCurrency(),
            $data->getFromAccount()->getCurrency(),
            $data->getAmountSent(),
            $rate
        );

        if (bccomp($this->abs(bcsub($data->getAmountReceived(), $expected, 2)), self::RATE_TOLERANCE, 2) > 0) {
            throw new MoneyTransferValidationException(sprintf(
                'По курсу %s из %s должно получиться %s, а указано %s — проверьте курс или суммы.',
                $rate,
                $data->getAmountSent(),
                $expected,
                $data->getAmountReceived()
            ));
        }
    }

    private function abs(string $value): string
    {
        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : $value;
    }
}
