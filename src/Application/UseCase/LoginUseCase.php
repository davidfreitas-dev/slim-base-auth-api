<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\LoginResponseDTO;
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

    public function execute(LoginRequestDTO $dto): LoginResponseDTO
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user) {
            throw new AuthenticationException('Credenciais inválidas.');
        }

        if (!$this->passwordHasher->verify($dto->password, $user->getPassword())) {
            throw new AuthenticationException('Credenciais inválidas.');
        }

        if (!$user->isActive()) {
            throw new AuthenticationException('A conta do usuário não está ativa.');
        }

        $accessToken = $this->jwtService->generateAccessToken($user->getId(), $user->getPerson()->getEmail());
        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        return new LoginResponseDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            tokenType: 'Bearer',
            expiresIn: $this->jwtService->getAccessTokenExpire(),
        );
    }
}
