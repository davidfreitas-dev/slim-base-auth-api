<?php

declare(strict_types=1);

namespace App\Application\DTO;

class LoginResponseDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
    ) {
    }
}
