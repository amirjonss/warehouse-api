<?php declare(strict_types=1);

namespace App\Component\Cash\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * The session number is taken: two sellers opened a session at the same moment and were
 * handed the same CS-XXXXX. Nothing is wrong with opening as such — retrying the request
 * picks up the next number.
 */
class SessionNumberTakenException extends ConflictHttpException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
