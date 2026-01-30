<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\ForgotPasswordRequestDTO;
use App\Application\UseCase\ForgotPasswordUseCase;
use App\Domain\Entity\PasswordReset;
use App\Domain\Entity\User;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Mailer\MailerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ForgotPasswordUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private LoggerInterface&MockObject $logger;

    private PasswordResetRepositoryInterface&MockObject $passwordResetRepository;

    private MailerInterface&MockObject $mailer;

    private ForgotPasswordUseCase $forgotPasswordUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);

        $this->forgotPasswordUseCase = new ForgotPasswordUseCase(
            $this->userRepository,
            $this->logger,
            $this->passwordResetRepository,
            $this->mailer
        );
    }

    public function testShouldSendPasswordResetEmailSuccessfully(): void
    {
        $requestDto = new ForgotPasswordRequestDTO('user@example.com', '127.0.0.1');
        
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn($user);

        $this->passwordResetRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(PasswordReset::class));

        $this->mailer->expects($this->once())->method('send');

        $this->forgotPasswordUseCase->execute($requestDto);
    }

    public function testShouldDoNothingForNonExistentUser(): void
    {
        $requestDto = new ForgotPasswordRequestDTO('nonexistent@example.com', '127.0.0.1');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $this->passwordResetRepository->expects($this->never())->method('save');
        $this->mailer->expects($this->never())->method('send');

        $this->forgotPasswordUseCase->execute($requestDto);
    }
}