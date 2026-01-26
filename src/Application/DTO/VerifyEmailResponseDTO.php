<?php

declare(strict_types=1);

namespace App\Application\DTO;

class VerifyEmailResponseDTO
{
    public function __construct(
        private readonly array $tokenData,
        private readonly bool $wasAlreadyVerified,
    ) {
    }

    public function getTokenData(): array
    {
        return $this->tokenData;
    }

    public function wasAlreadyVerified(): bool
    {
        return $this->wasAlreadyVerified;
    }
}
