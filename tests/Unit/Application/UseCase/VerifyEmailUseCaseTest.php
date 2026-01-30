<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\VerifyEmailResponseDTO;
use App\Application\UseCase\VerifyEmailUseCase;
use App\Domain\Entity\User;
use App\Domain\Entity\UserVerification;
use App\Domain\Enum\JsonResponseKey;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class VerifyEmailUseCaseTest extends TestCase
{
    private UserVerificationRepositoryInterface&MockObject $userVerificationRepository;

    private UserRepositoryInterface&MockObject $userRepository;

    private JwtService&MockObject $jwtService;

    private VerifyEmailUseCase $verifyEmailUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userVerificationRepository = $this->createMock(UserVerificationRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->jwtService = $this->createMock(JwtService::class);
        $this->verifyEmailUseCase = new VerifyEmailUseCase($this->userVerificationRepository, $this->userRepository, $this->jwtService);
    }

    public function testShouldVerifyEmailAndReturnTokensSuccessfully(): void
    {
        $token = 'valid-token';
        $userId = 1;

        /** @var UserVerification&MockObject $verification */
        $verification = $this->createMock(UserVerification::class);
        $verification->method('getUserId')->willReturn($userId);
        $verification->method('isUsed')->willReturn(false);
        $verification->method('isExpired')->willReturn(false);
        $this->userVerificationRepository->method('findByToken')->with($token)->willReturn($verification);

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('isVerified')->willReturn(false);
        $user->method('getId')->willReturn($userId);
        $this->userRepository->method('findById')->with($userId)->willReturn($user);

        $this->userRepository->expects($this->once())->method('markUserAsVerified')->with($userId);
        $this->userVerificationRepository->expects($this->once())->method('markAsUsed')->with($token);

        $this->jwtService->method('generateAccessToken')->willReturn('new_access_token');
        $this->jwtService->method('generateRefreshToken')->willReturn('new_refresh_token');
        $this->jwtService->method('getAccessTokenExpire')->willReturn(3600);

        $result = $this->verifyEmailUseCase->execute($token);

        $this->assertInstanceOf(VerifyEmailResponseDTO::class, $result);
        $this->assertFalse($result->wasAlreadyVerified());

        $tokenData = $result->getTokenData();
        $this->assertIsArray($tokenData);
        $this->assertSame('new_access_token', $tokenData[JsonResponseKey::ACCESS_TOKEN->value]);
        $this->assertSame('new_refresh_token', $tokenData[JsonResponseKey::REFRESH_TOKEN->value]);
        $this->assertSame('Bearer', $tokenData[JsonResponseKey::TOKEN_TYPE->value]);
        $this->assertSame(3600, $tokenData[JsonResponseKey::EXPIRES_IN->value]);
    }

    public function testShouldThrowNotFoundExceptionForInvalidToken(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Token de verificação inválido.');

        $this->userVerificationRepository->method('findByToken')->with('invalid-token')->willReturn(null);
        $this->verifyEmailUseCase->execute('invalid-token');
    }

    public function testShouldThrowValidationExceptionForUsedToken(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O token de verificação já foi utilizado.');

        /** @var UserVerification&MockObject $verification */
        $verification = $this->createMock(UserVerification::class);
        $verification->method('isUsed')->willReturn(true);
        $this->userVerificationRepository->method('findByToken')->willReturn($verification);

        $this->verifyEmailUseCase->execute('used-token');
    }

    public function testShouldThrowValidationExceptionForExpiredToken(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O token de verificação expirou.');

        /** @var UserVerification&MockObject $verification */
        $verification = $this->createMock(UserVerification::class);
        $verification->method('isUsed')->willReturn(false);
        $verification->method('isExpired')->willReturn(true);
        $this->userVerificationRepository->method('findByToken')->willReturn($verification);

        $this->verifyEmailUseCase->execute('expired-token');
    }

    public function testShouldReturnTokensForAlreadyVerifiedUser(): void
    {
        $token = 'valid-token';
        $userId = 1;

        /** @var UserVerification&MockObject $verification */
        $verification = $this->createMock(UserVerification::class);
        $verification->method('getUserId')->willReturn($userId);
        $verification->method('isUsed')->willReturn(false);
        $verification->method('isExpired')->willReturn(false);
        $this->userVerificationRepository->method('findByToken')->with($token)->willReturn($verification);

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('isVerified')->willReturn(true);
        $user->method('getId')->willReturn($userId);
        $this->userRepository->method('findById')->with($userId)->willReturn($user);

        $this->userRepository->expects($this->never())->method('markUserAsVerified');
        $this->userVerificationRepository->expects($this->never())->method('markAsUsed');

        $this->jwtService->method('generateAccessToken')->willReturn('refreshed_access_token');
        $this->jwtService->method('generateRefreshToken')->willReturn('refreshed_refresh_token');
        $this->jwtService->method('getAccessTokenExpire')->willReturn(3600);

        $result = $this->verifyEmailUseCase->execute($token);

        $this->assertInstanceOf(VerifyEmailResponseDTO::class, $result);
        $this->assertTrue($result->wasAlreadyVerified());

        $tokenData = $result->getTokenData();
        $this->assertIsArray($tokenData);
        $this->assertSame('refreshed_access_token', $tokenData[JsonResponseKey::ACCESS_TOKEN->value]);
        $this->assertSame('refreshed_refresh_token', $tokenData[JsonResponseKey::REFRESH_TOKEN->value]);
    }

    public function testShouldThrowNotFoundExceptionForNonExistentUser(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Usuário não encontrado.');

        $token = 'valid-token';
        $userId = 999;

        /** @var UserVerification&MockObject $verification */
        $verification = $this->createMock(UserVerification::class);
        $verification->method('getUserId')->willReturn($userId);
        $verification->method('isUsed')->willReturn(false);
        $verification->method('isExpired')->willReturn(false);
        $this->userVerificationRepository->method('findByToken')->with($token)->willReturn($verification);

        $this->userRepository->method('findById')->with($userId)->willReturn(null);

        $this->verifyEmailUseCase->execute($token);
    }
}