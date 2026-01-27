<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UserListResponseDTO;
use App\Application\DTO\UserResponseDTO;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(int $limit = 20, int $offset = 0): UserListResponseDTO
    {
        $users = $this->userRepository->findAll($limit, $offset);
        $total = $this->userRepository->count(); // Using the existing count method

        $userDTOs = \array_map(
            static fn (User $user): UserResponseDTO => new UserResponseDTO(
                id: $user->getId(),
                name: $user->getPerson()->getName(),
                email: $user->getPerson()->getEmail(),
                roleName: $user->getRole()->getName(),
                isActive: $user->isActive(),
                isVerified: $user->isVerified(),
                phone: $user->getPerson()->getPhone(),
                cpfcnpj: $user->getPerson()->getCpfCnpj() ? $user->getPerson()->getCpfCnpj()->value() : null,
            ),
            $users,
        );

        return new UserListResponseDTO(
            users: $userDTOs,
            total: $total,
            limit: $limit,
            offset: $offset,
        );
    }
}
