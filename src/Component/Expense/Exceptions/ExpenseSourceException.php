<?php declare(strict_types=1);

namespace App\Component\Expense\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * An expense that names no money it came out of. Before the treasury existed such an
 * expense was simply recorded and nothing moved; now that there is somewhere for the
 * money to leave from, an unattached expense would quietly overstate the company's cash.
 */
class ExpenseSourceException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
