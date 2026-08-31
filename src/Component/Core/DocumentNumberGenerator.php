<?php

declare(strict_types=1);

namespace App\Component\Core;

/**
 * Document numbers are a per-type sequence rendered as PREFIX + zero-padded counter.
 * The last number is looked up by the caller's repository, so this stays pure and the
 * factory keeps owning its own prefix.
 */
class DocumentNumberGenerator
{
    public function next(string $prefix, int $length, ?string $lastNumber): string
    {
        $sequence = 1;
        if ($lastNumber !== null && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $sequence, $length, '0', STR_PAD_LEFT);
    }
}
