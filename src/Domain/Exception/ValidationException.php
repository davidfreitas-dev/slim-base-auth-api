<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

class ValidationException extends Exception
{
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
