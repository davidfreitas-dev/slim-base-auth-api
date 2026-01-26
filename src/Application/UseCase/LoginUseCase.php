<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\LoginRequestDTO;
use App\Domain\Enum\JsonResponseKey;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly JwtService $jwtService,
    ) {
    }

    public function execute(LoginRequestDTO $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user) {
            throw new AuthenticationException('Invalid credentials');
        }

        if (!$this->passwordHasher->verify($dto->password, $user->getPassword())) {
            throw new AuthenticationException('Invalid credentials');
        }

        if (!$user->isActive()) {
            throw new AuthenticationException('User account is not active');
        }

        $accessToken = $this->jwtService->generateAccessToken($user->getId(), $user->getPerson()->getEmail());
        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        return [
            JsonResponseKey::ACCESS_TOKEN->value => $accessToken,
            JsonResponseKey::REFRESH_TOKEN->value => $refreshToken,
            JsonResponseKey::TOKEN_TYPE->value => 'Bearer',
            JsonResponseKey::EXPIRES_IN->value => $this->jwtService->getAccessTokenExpire(),
        ];
    }
}
