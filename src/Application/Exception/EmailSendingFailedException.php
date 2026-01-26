<?php

declare(strict_types=1);

namespace App\Application\Exception;

use Exception;
use Throwable;

class EmailSendingFailedException extends Exception
{
    public function __construct(string $message = 'Failed to send email.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
