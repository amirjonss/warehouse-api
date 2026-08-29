<?php declare(strict_types=1);

namespace App\Component\Core\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * A report was asked for with parameters it cannot honour — an unknown metric, a date in the
 * wrong shape, thresholds in the wrong order. Every report reads its arguments from the
 * query string, so this is the one place that turns a bad one into a 422.
 */
class InvalidReportCriteriaException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
