<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\ValidateResetCodeRequestDTO;
use App\Application\UseCase\ValidateResetCodeUseCase;
use App\Domain\Entity\PasswordReset;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Code;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ValidateResetCodeUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $passwordResetRepository;

    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $logger;

    private ValidateResetCodeUseCase $validateResetCodeUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->validateResetCodeUseCase = new ValidateResetCodeUseCase(
            $this->passwordResetRepository,
            $this->userRepository,
            $this->logger
        );
    }

    public function testShouldValidateCodeSuccessfully(): void
    {
        $dto = new ValidateResetCodeRequestDTO('test@example.com', '123456');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $this->userRepository->method('findByEmail')->with('test@example.com')->willReturn($user);

        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->method('getUserId')->willReturn(1);
        $this->passwordResetRepository->method('findByCode')->with(Code::from('123456'))->willReturn($passwordReset);

        $result = $this->validateResetCodeUseCase->execute($dto);

        $this->assertSame($passwordReset, $result);
    }

    public function testShouldThrowNotFoundExceptionForUnknownEmail(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Invalid email or code.');

        $dto = new ValidateResetCodeRequestDTO('unknown@example.com', '654321');
        $this->userRepository->method('findByEmail')->with('unknown@example.com')->willReturn(null);

        $this->validateResetCodeUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionForInvalidCode(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Invalid email or code.');

        $dto = new ValidateResetCodeRequestDTO('test@example.com', '111111');

        $user = $this->createMock(User::class);
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordResetRepository->method('findByCode')->willReturn(null);

        $this->validateResetCodeUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionIfCodeUserMismatch(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Invalid email or code.');

        $dto = new ValidateResetCodeRequestDTO('test@example.com', '123456');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1); // User has ID 1
        $this->userRepository->method('findByEmail')->willReturn($user);

        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->method('getUserId')->willReturn(2); // But code belongs to user ID 2
        $this->passwordResetRepository->method('findByCode')->willReturn($passwordReset);

        $this->validateResetCodeUseCase->execute($dto);
    }
}
