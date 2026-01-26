<?php

declare(strict_types=1);

namespace App\Application\DTO;

class ForgotPasswordRequestDTO
{
    public function __construct(
        private readonly string $email,
        private readonly string $ipAddress,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
}
