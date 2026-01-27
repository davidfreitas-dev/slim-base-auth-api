<?php

declare(strict_types=1);

namespace App\Application\DTO;

class UserResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $roleName,
        public readonly bool $isActive,
        public readonly bool $isVerified,
        public readonly ?string $phone = null,
        public readonly ?string $cpfcnpj = null,
    ) {
    }
}
