<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\ResetPasswordRequestDTO;
use App\Domain\Entity\PasswordReset;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
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

    public function execute(PasswordReset $passwordReset, ResetPasswordRequestDTO $dto): bool
    {
        $user = $this->userRepository->findById($passwordReset->getUserId());

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('User not found');
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hash($dto->password);
        $this->userRepository->updatePassword($user->getId(), $hashedPassword);

        // Invalidate all user's refresh tokens
        $this->jwtService->invalidateAllUserRefreshTokens($user->getId());

        // Mark token as used
        $this->passwordResetRepository->markAsUsed($passwordReset->getCode());

        return true;
    }
}
