<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\PasswordResetResponseDTO;
use App\Application\DTO\ResetPasswordRequestDTO;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Code;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;

class ResetPasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly JwtService $jwtService,
    ) {
    }

    public function execute(PasswordResetResponseDTO $passwordResetDto, ResetPasswordRequestDTO $dto): bool
    {
        $user = $this->userRepository->findById($passwordResetDto->userId);

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('Usuário não encontrado.');
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hash($dto->password);
        $this->userRepository->updatePassword($user->getId(), $hashedPassword);

        // Invalidate all user's refresh tokens
        $this->jwtService->invalidateAllUserRefreshTokens($user->getId());

        // Mark token as used
        $this->passwordResetRepository->markAsUsed(Code::from($passwordResetDto->code)); // Convert DTO code string back to Code Value Object

        return true;
    }
}
