<?php

declare(strict_types=1);

namespace App\Application\DTO;

class UserListResponseDTO
{
    /**
     * @param UserResponseDTO[] $users
     */
    public function __construct(
        public readonly array $users,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
