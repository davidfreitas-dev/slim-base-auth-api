<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\PasswordResetResponseDTO;
use App\Application\DTO\ValidateResetCodeRequestDTO;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Code;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class ValidateResetCodeUseCase
{
    public function __construct(
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(ValidateResetCodeRequestDTO $request): PasswordResetResponseDTO
    {
        $user = $this->userRepository->findByEmail($request->email);
        if (!$user instanceof \App\Domain\Entity\User) {
            $this->logger->warning('Password reset code validation failed for unknown email', ['email' => $request->email]);

            throw new NotFoundException('Invalid email or code.');
        }

        $passwordReset = $this->passwordResetRepository->findByCode(Code::from($request->code));

        if (!$passwordReset || $passwordReset->getUserId() !== $user->getId()) {
            $this->logger->warning('Password reset code validation failed', [
                'email' => $request->email,
                'code' => $request->code,
            ]);

            throw new NotFoundException('Invalid email or code.');
        }

        return new PasswordResetResponseDTO(
            id: $passwordReset->getId(),
            userId: $passwordReset->getUserId(),
            code: $passwordReset->getCode()->value,
            expiresAt: $passwordReset->getExpiresAt()->format(DateTimeImmutable::ATOM),
            usedAt: $passwordReset->getUsedAt()?->format(DateTimeImmutable::ATOM),
            ipAddress: $passwordReset->getIpAddress(),
        );
    }
}
