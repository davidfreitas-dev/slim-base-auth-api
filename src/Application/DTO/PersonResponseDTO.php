<?php

declare(strict_types=1);

namespace App\Application\DTO;

class PersonResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $cpfcnpj,
        public readonly ?string $avatarUrl,
    ) {
    }
}
