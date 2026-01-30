<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\ChangePasswordRequestDTO;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Mailer\MailerInterface;
use App\Infrastructure\Mailer\PasswordChangedEmailTemplate;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;

class ChangePasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly JwtService $jwtService,
    ) {
    }

    public function execute(ChangePasswordRequestDTO $dto): void
    {
        $user = $this->userRepository->findById($dto->userId);

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('Usuário não encontrado.');
        }

        if (!$this->passwordHasher->verify($dto->currentPassword, $user->getPassword())) {
            throw new ValidationException('A senha atual não confere.');
        }

        $newPasswordHash = $this->passwordHasher->hash($dto->newPassword);
        $user->setPassword($newPasswordHash);

        $this->userRepository->update($user);

        // Invalidate all user's refresh tokens
        $this->jwtService->invalidateAllUserRefreshTokens($user->getId());

        // Send email notification
        $this->mailer->send(
            new PasswordChangedEmailTemplate(
                $user->getPerson()->getEmail(),
                $user->getPerson()->getName(),
            ),
        );
    }
}
