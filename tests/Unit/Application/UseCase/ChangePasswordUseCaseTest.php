<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\ChangePasswordRequestDTO;
use App\Application\UseCase\ChangePasswordUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Mailer\MailerInterface;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ChangePasswordUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $passwordHasher;

    private \PHPUnit\Framework\MockObject\MockObject $mailer;

    private \PHPUnit\Framework\MockObject\MockObject $jwtService;

    private ChangePasswordUseCase $changePasswordUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasher::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->jwtService = $this->createMock(JwtService::class);
        $this->changePasswordUseCase = new ChangePasswordUseCase(
            $this->userRepository,
            $this->passwordHasher,
            $this->mailer,
            $this->jwtService
        );
    }

    public function testShouldChangePasswordSuccessfully(): void
    {
        $dto = new ChangePasswordRequestDTO(1, 'old-password', 'new-password', 'new-password');

        $user = $this->createMock(User::class);
        $user->method('getPassword')->willReturn('hashed-old-password');

        $this->userRepository->method('findById')->with(1)->willReturn($user);
        $this->passwordHasher->method('verify')->with('old-password', 'hashed-old-password')->willReturn(true);
        $this->passwordHasher->method('hash')->with('new-password')->willReturn('hashed-new-password');

        $user->expects($this->once())->method('setPassword')->with('hashed-new-password');
        $this->userRepository->expects($this->once())->method('update')->with($user);

        $this->changePasswordUseCase->execute($dto);

        // No exception means success
        $this->assertTrue(true);
    }

    public function testShouldThrowValidationExceptionForWrongOldPassword(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A senha atual não confere.');

        $dto = new ChangePasswordRequestDTO(1, 'wrong-old-password', 'new-password', 'new-password');

        $user = $this->createMock(User::class);
        $user->method('getPassword')->willReturn('hashed-old-password');

        $this->userRepository->method('findById')->with(1)->willReturn($user);
        $this->passwordHasher->method('verify')->with('wrong-old-password', 'hashed-old-password')->willReturn(false);

        $this->changePasswordUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionIfUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Usuário não encontrado.');

        $dto = new ChangePasswordRequestDTO(999, 'old-password', 'new-password', 'new-password');

        $this->userRepository->method('findById')->with(999)->willReturn(null);

        $this->changePasswordUseCase->execute($dto);
    }
}
