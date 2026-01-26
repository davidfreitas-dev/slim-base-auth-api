<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

class ConflictException extends Exception
{
    public function getStatusCode(): int
    {
        return 409;
    }
}
