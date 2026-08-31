<?php declare(strict_types=1);

namespace App\Component\Account\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class OpeningBalanceAlreadySetException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
