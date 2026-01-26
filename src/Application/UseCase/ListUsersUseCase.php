<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Repository\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(int $limit = 20, int $offset = 0): array
    {
        return $this->userRepository->findAll($limit, $offset);
    }
}
