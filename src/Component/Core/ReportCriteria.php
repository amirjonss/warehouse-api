<?php

declare(strict_types=1);

namespace App\Component\Core;

use App\Component\Core\Exceptions\InvalidReportCriteriaException;
use App\Component\Product\Enums\Currency;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the arguments every report shares — period, currency, category — from the query
 * string, and refuses anything malformed with a 422 instead of letting it reach SQL.
 */
class ReportCriteria
{
    public function date(?Request $request, string $key): ?string
    {
        $raw = $this->raw($request, $key);
        if ($raw === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        if ($date === false) {
            throw new InvalidReportCriteriaException(sprintf('Дата «%s» должна быть в формате ГГГГ-ММ-ДД.', $raw));
        }

        return $date->format('Y-m-d');
    }

    public function currency(?Request $request, string $key = 'currency'): ?Currency
    {
        $raw = $this->raw($request, $key);
        if ($raw === null) {
            return null;
        }

        $currency = Currency::tryFrom($raw);

        if ($currency === null) {
            throw new InvalidReportCriteriaException(sprintf(
                'Неизвестная валюта «%s». Допустимые: %s.',
                $raw,
                implode(', ', array_column(Currency::cases(), 'value'))
            ));
        }

        return $currency;
    }

    /**
     * Money reports have to name a currency. Dollars and sums are never summed here, and
     * converting them behind the user's back would mean inventing a rate for a period the
     * rate moved through.
     */
    public function requiredCurrency(?Request $request, string $key = 'currency'): Currency
    {
        $currency = $this->currency($request, $key);

        if ($currency === null) {
            throw new InvalidReportCriteriaException(sprintf(
                'Укажите валюту (%s): доллары и сумы в одном отчёте не складываются.',
                implode(' или ', array_column(Currency::cases(), 'value'))
            ));
        }

        return $currency;
    }

    public function categoryId(?Request $request, string $key = 'category'): ?int
    {
        $raw = $this->raw($request, $key);
        if ($raw === null) {
            return null;
        }

        if (!ctype_digit($raw)) {
            throw new InvalidReportCriteriaException('Категория задаётся числовым идентификатором.');
        }

        return (int) $raw;
    }

    public function float(?Request $request, string $key, float $default): float
    {
        $raw = $this->raw($request, $key);
        if ($raw === null) {
            return $default;
        }

        if (!is_numeric($raw)) {
            throw new InvalidReportCriteriaException(sprintf('Порог «%s» должен быть числом.', $key));
        }

        return (float) $raw;
    }

    /** An absent parameter and an empty one mean the same thing: not set. */
    private function raw(?Request $request, string $key): ?string
    {
        $value = $request?->query->get($key);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
