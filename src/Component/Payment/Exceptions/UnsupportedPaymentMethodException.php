<?php declare(strict_types=1);

namespace App\Component\Payment\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class UnsupportedPaymentMethodException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
