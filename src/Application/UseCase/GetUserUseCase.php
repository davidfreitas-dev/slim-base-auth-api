<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UserResponseDTO;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\UserRepositoryInterface;

class GetUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(int $userId): UserResponseDTO
    {
        $user = $this->userRepository->findById($userId);

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('User not found.');
        }

        return new UserResponseDTO(
            id: $user->getId(),
            name: $user->getPerson()->getName(),
            email: $user->getPerson()->getEmail(),
            roleName: $user->getRole()->getName(),
            isActive: $user->isActive(),
            isVerified: $user->isVerified(),
            phone: $user->getPerson()->getPhone(),
            cpfcnpj: $user->getPerson()->getCpfCnpj() ? $user->getPerson()->getCpfCnpj()->value() : null,
        );
    }
}
