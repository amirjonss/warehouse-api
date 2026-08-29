<?php

declare(strict_types=1);

namespace App\Component\Product;

use App\Component\Product\Dtos\AbcAnalysisDto;
use App\Component\Product\Dtos\AbcClassSummaryDto;
use App\Component\Product\Dtos\AbcItemDto;
use App\Component\Product\Enums\AbcMetric;

/**
 * Turns a ranked list of per-product totals into A/B/C classes.
 *
 * The thresholds are shares of the *total*, not of the product count — the most common
 * misreading of this report. Walking the list from the biggest down, everything until the
 * accumulated share reaches thresholdA is class A, up to thresholdB is B, the rest is C.
 * There is no threshold for C: it is whatever is left, which is why the field is disabled
 * in every UI that offers these settings.
 */
class AbcClassifier
{
    private const CLASSES = ['A', 'B', 'C'];
    private const SCALE = 2;

    /**
     * @param array<int, array{id: int|string, name: string, category_name: string|null, unit: string|null, value: string|null}> $rows
     *        already sorted by value descending
     */
    public function classify(
        array $rows,
        AbcMetric $metric,
        ?string $baseCurrency,
        float $thresholdA,
        float $thresholdB,
    ): AbcAnalysisDto {
        $scale = $metric === AbcMetric::QUANTITY ? 3 : self::SCALE;

        // Only positive values make up the whole. A product that lost money or was fully
        // reversed cannot contribute a negative slice to a cumulative percentage.
        $total = '0';
        foreach ($rows as $row) {
            $value = $this->normalise($row['value'] ?? '0', $scale);
            if (bccomp($value, '0', $scale) > 0) {
                $total = bcadd($total, $value, $scale);
            }
        }

        $items = [];
        $cumulative = '0';

        foreach ($rows as $row) {
            $value = $this->normalise($row['value'] ?? '0', $scale);
            $positive = bccomp($value, '0', $scale) > 0 && bccomp($total, '0', $scale) > 0;

            // The class is decided by what was accumulated *before* this product, not after.
            // A product straddling the line is the one that gets you to the threshold, so it
            // belongs to the class it completes. Deciding on the share after it were added
            // would put a lone product owning 100 % of the revenue into C.
            $cumulativeBefore = $cumulative;

            if ($positive) {
                $cumulative = bcadd($cumulative, $value, $scale);
            }

            $share = $positive ? $this->percent($value, $total) : '0.00';
            $cumulativeShare = $positive ? $this->percent($cumulative, $total) : '100.00';

            $items[] = new AbcItemDto(
                (int) $row['id'],
                (string) $row['name'],
                $row['category_name'] ?? null,
                $row['unit'] ?? null,
                $value,
                $share,
                $cumulativeShare,
                // Everything unprofitable lands in C regardless of where the cursor is.
                $positive
                    ? $this->classFor((float) $this->percent($cumulativeBefore, $total), $thresholdA, $thresholdB)
                    : 'C',
            );
        }

        return new AbcAnalysisDto(
            $metric->value,
            $baseCurrency,
            $total,
            $items,
            $this->summarise($items, $total, $scale),
        );
    }

    private function classFor(float $cumulativeBefore, float $thresholdA, float $thresholdB): string
    {
        if ($cumulativeBefore < $thresholdA) {
            return 'A';
        }

        return $cumulativeBefore < $thresholdB ? 'B' : 'C';
    }

    /**
     * @param AbcItemDto[] $items
     *
     * @return AbcClassSummaryDto[]
     */
    private function summarise(array $items, string $total, int $scale): array
    {
        $counts = array_fill_keys(self::CLASSES, 0);
        $values = array_fill_keys(self::CLASSES, '0');

        foreach ($items as $item) {
            $counts[$item->class]++;
            // Negative values would distort the class total, and they are already excluded
            // from the whole, so they are excluded here too.
            if (bccomp($item->value, '0', $scale) > 0) {
                $values[$item->class] = bcadd($values[$item->class], $item->value, $scale);
            }
        }

        $summary = [];
        foreach (self::CLASSES as $class) {
            $summary[] = new AbcClassSummaryDto(
                $class,
                $counts[$class],
                $values[$class],
                $this->percent($values[$class], $total),
            );
        }

        return $summary;
    }

    private function percent(string $part, string $total): string
    {
        if (bccomp($total, '0', self::SCALE) <= 0) {
            return '0.00';
        }

        return bcdiv(bcmul($part, '100', 6), $total, self::SCALE);
    }

    private function normalise(?string $value, int $scale): string
    {
        return bcadd($value ?? '0', '0', $scale);
    }
}
