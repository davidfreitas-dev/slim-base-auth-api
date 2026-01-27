<?php

declare(strict_types=1);

namespace App\Application\DTO;

use DateTimeImmutable;

class ErrorLogResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context,
        public readonly ?string $resolvedAt,
        public readonly ?int $resolvedByUserId,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
