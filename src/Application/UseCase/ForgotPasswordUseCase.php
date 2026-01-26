<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\ForgotPasswordRequestDTO;
use App\Application\Exception\EmailSendingFailedException;
use App\Domain\Entity\PasswordReset;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Code;
use App\Infrastructure\Mailer\MailerInterface;
use App\Infrastructure\Mailer\PasswordResetEmailTemplate;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class ForgotPasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
        private readonly PasswordResetRepositoryInterface $passwordResetRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function execute(ForgotPasswordRequestDTO $request): void
    {
        $user = $this->userRepository->findByEmail($request->getEmail());

        if (!$user instanceof \App\Domain\Entity\User) {
            $this->logger->info('Password recovery attempt for unknown email', ['email' => $request->getEmail()]);

            // Avoid revealing if email exists for security reasons
            return;
        }

        // Ensure the User entity has its Person relationship loaded
        // For now, assuming user->getPerson() is available.
        $person = $user->getPerson();
        if (!$person) {
            $this->logger->error(sprintf('User with email %s has no associated person data.', $user->getEmail()), ['user_id' => $user->getId()]);

            return; // Or throw an exception
        }

        // Generate a secure, random code for password reset
        $code = Code::generate();
        $expiresAt = new DateTimeImmutable()->modify('+1 hour');

        $passwordReset = new PasswordReset(
            null, // ID is auto-generated
            $user->getId(),
            $code,
            $expiresAt,
            null, // Set usedAt to null as it's not used yet
            $request->getIpAddress(), // Pass the IP address from the DTO
        );

        $this->passwordResetRepository->save($passwordReset);
        $this->logger->info('Password reset code generated and saved for user ID: ' . $user->getId());

        try {
            $this->mailer->send(
                new PasswordResetEmailTemplate(
                    $user->getEmail(),
                    $person->getName(),
                    $code->value,
                ),
            );
            $this->logger->info('Password reset email sent', ['email' => $user->getEmail()]);
        } catch (EmailSendingFailedException $emailSendingFailedException) { // Changed to EmailSendingFailedException
            $this->logger->error(sprintf('Failed to send password reset email to %s: %s', $user->getEmail(), $emailSendingFailedException->getMessage()));

            throw $emailSendingFailedException; // Rethrow the specific exception
        }
    }
}
