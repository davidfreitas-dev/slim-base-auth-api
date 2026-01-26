<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\UserRepositoryInterface;

class GetUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(int $userId): User
    {
        $user = $this->userRepository->findById($userId);

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('User not found.');
        }

        return $user;
    }
}
