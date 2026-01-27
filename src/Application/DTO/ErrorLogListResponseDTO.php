<?php

declare(strict_types=1);

namespace App\Application\DTO;

class ErrorLogListResponseDTO
{
    /**
     * @param ErrorLogResponseDTO[] $errorLogs
     */
    public function __construct(
        public readonly array $errorLogs,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
