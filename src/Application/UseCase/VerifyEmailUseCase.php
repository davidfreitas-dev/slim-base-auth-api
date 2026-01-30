<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\VerifyEmailResponseDTO;
use App\Domain\Enum\JsonResponseKey;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Infrastructure\Security\JwtService;

class VerifyEmailUseCase
{
    public function __construct(
        private readonly UserVerificationRepositoryInterface $userVerificationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
    ) {
    }

    public function execute(string $token): VerifyEmailResponseDTO
    {
        $verification = $this->userVerificationRepository->findByToken($token);

        if (!$verification instanceof \App\Domain\Entity\UserVerification) {
            throw new NotFoundException('Token de verificação inválido.');
        }

        if ($verification->isUsed()) {
            throw new ValidationException('O token de verificação já foi utilizado.');
        }

        if ($verification->isExpired()) {
            throw new ValidationException('O token de verificação expirou.');
        }

        $user = $this->userRepository->findById($verification->getUserId());

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('Usuário não encontrado.');
        }

        $wasAlreadyVerified = $user->isVerified();

        if (!$wasAlreadyVerified) {
            // Mark user as verified
            $this->userRepository->markUserAsVerified($user->getId());

            // Mark verification token as used
            $this->userVerificationRepository->markAsUsed($token);
        }

        // Generate a new access token with the updated 'is_verified' status
        $accessToken = $this->jwtService->generateAccessToken($user->getId(), $user->getEmail());
        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        $tokenData = [
            JsonResponseKey::ACCESS_TOKEN->value => $accessToken,
            JsonResponseKey::REFRESH_TOKEN->value => $refreshToken,
            JsonResponseKey::TOKEN_TYPE->value => 'Bearer',
            JsonResponseKey::EXPIRES_IN->value => $this->jwtService->getAccessTokenExpire(),
        ];

        return new VerifyEmailResponseDTO($tokenData, $wasAlreadyVerified);
    }
}
