<?php

declare(strict_types=1);

namespace App\Application\DTO;

class PasswordResetResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $code,
        public readonly string $expiresAt,
        public readonly ?string $usedAt,
        public readonly string $ipAddress,
    ) {
    }
}
