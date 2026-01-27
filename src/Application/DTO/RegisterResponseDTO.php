<?php

declare(strict_types=1);

namespace App\Application\DTO;

class RegisterResponseDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $userRoleName,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
    ) {
    }
}
