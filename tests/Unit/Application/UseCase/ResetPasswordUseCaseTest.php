<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\ResetPasswordRequestDTO;
use App\Application\UseCase\ResetPasswordUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ResetPasswordUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $userRepository;
    private PasswordResetRepositoryInterface|MockObject $passwordResetRepository;
    private PasswordHasher|MockObject $passwordHasher;
    private JwtService|MockObject $jwtService;
    private ResetPasswordUseCase $resetPasswordUseCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasher::class);
        $this->jwtService = $this->createMock(JwtService::class);

        $this->resetPasswordUseCase = new ResetPasswordUseCase(
            $this->userRepository,
            $this->passwordResetRepository,
            $this->passwordHasher,
            $this->jwtService
        );
    }

    public function testShouldResetPasswordSuccessfully(): void
    {
        $dto = new ResetPasswordRequestDTO(
            email: 'test@example.com',
            code: '123456',
            password: 'newPassword123',
            passwordConfirm: 'newPassword123'
        );


        $passwordReset = $this->createMock(\App\Domain\Entity\PasswordReset::class);
        $passwordReset->method('getUserId')->willReturn(1);
        $passwordReset->method('getCode')->willReturn(\App\Domain\ValueObject\Code::from('123456'));

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($user);
        $this->passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with($dto->password) // Use DTO password
            ->willReturn('hashed-password');
        $this->userRepository
            ->expects($this->once())
            ->method('updatePassword')
            ->with(1, 'hashed-password');
        $this->jwtService
            ->expects($this->once())
            ->method('invalidateAllUserRefreshTokens')
            ->with(1);
        $this->passwordResetRepository
            ->expects($this->once())
            ->method('markAsUsed')
            ->with($passwordReset->getCode()); // Call getCode on the mock

        $result = $this->resetPasswordUseCase->execute($passwordReset, $dto); // Correct arguments

        $this->assertTrue($result);
    }

    public function testShouldThrowValidationExceptionIfUserNotFound(): void
    {
        $dto = new ResetPasswordRequestDTO(
            email: 'test@example.com',
            code: '123456',
            password: 'newPassword123',
            passwordConfirm: 'newPassword123'
        );

        $passwordReset = $this->createMock(\App\Domain\Entity\PasswordReset::class);
        $passwordReset->method('getUserId')->willReturn(999);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->resetPasswordUseCase->execute($passwordReset, $dto);
    }

}