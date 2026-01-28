<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\PasswordResetResponseDTO;
use App\Application\DTO\ValidateResetCodeRequestDTO;
use App\Application\UseCase\ValidateResetCodeUseCase;
use App\Domain\Entity\PasswordReset;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Code;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ValidateResetCodeUseCaseTest extends TestCase
{
    private PasswordResetRepositoryInterface&MockObject $passwordResetRepository;

    private UserRepositoryInterface&MockObject $userRepository;

    private LoggerInterface&MockObject $logger;

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

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $this->userRepository->method('findByEmail')->with('test@example.com')->willReturn($user);

        // Create a real PasswordReset entity to avoid mocking final Code class
        $expectedCode = Code::from('123456');
        $expectedExpiresAt = new DateTimeImmutable('+1 hour');
        $expectedPasswordReset = new PasswordReset(
            id: 1,
            userId: 1,
            code: $expectedCode,
            expiresAt: $expectedExpiresAt,
            usedAt: null,
            ipAddress: '127.0.0.1'
        );

        $this->passwordResetRepository->expects($this->once())
            ->method('findByCode')
            ->with($expectedCode)
            ->willReturn($expectedPasswordReset);

        $result = $this->validateResetCodeUseCase->execute($dto);

        $this->assertInstanceOf(PasswordResetResponseDTO::class, $result);
        $this->assertEquals($expectedPasswordReset->getId(), $result->id);
        $this->assertEquals($expectedPasswordReset->getUserId(), $result->userId);
        $this->assertEquals($expectedPasswordReset->getCode()->value, $result->code);
        $this->assertEquals($expectedPasswordReset->getExpiresAt()->format(DateTimeImmutable::ATOM), $result->expiresAt);
        $this->assertEquals($expectedPasswordReset->getUsedAt(), $result->usedAt);
        $this->assertEquals($expectedPasswordReset->getIpAddress(), $result->ipAddress);
    }

    public function testShouldThrowNotFoundExceptionForUnknownEmail(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('E-mail ou código inválido.');

        $dto = new ValidateResetCodeRequestDTO('unknown@example.com', '654321');
        $this->userRepository->method('findByEmail')->with('unknown@example.com')->willReturn(null);

        $this->validateResetCodeUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionForInvalidCode(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('E-mail ou código inválido.');

        $dto = new ValidateResetCodeRequestDTO('test@example.com', '111111');

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordResetRepository->method('findByCode')->willReturn(null);

        $this->validateResetCodeUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionIfCodeUserMismatch(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('E-mail ou código inválido.');

        $dto = new ValidateResetCodeRequestDTO('test@example.com', '123456');

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1); // User has ID 1
        $this->userRepository->method('findByEmail')->willReturn($user);

        // Create a real PasswordReset entity with a different userId
        $expectedCode = Code::from('123456');
        $expectedExpiresAt = new DateTimeImmutable('+1 hour');
        $passwordResetForDifferentUser = new PasswordReset(
            id: 2,
            userId: 2, // Code belongs to user ID 2
            code: $expectedCode,
            expiresAt: $expectedExpiresAt,
            usedAt: null,
            ipAddress: '127.0.0.1'
        );

        $this->passwordResetRepository->expects($this->once())
            ->method('findByCode')
            ->with($expectedCode)
            ->willReturn($passwordResetForDifferentUser);

        $this->validateResetCodeUseCase->execute($dto);
    }
}