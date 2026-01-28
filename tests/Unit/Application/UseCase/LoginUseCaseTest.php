<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\LoginResponseDTO;
use App\Application\UseCase\LoginUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use App\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class LoginUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $userRepository;
    private PasswordHasher|MockObject $passwordHasher;
    private JwtService|MockObject $jwtService;
    private LoginUseCase $loginUseCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasher::class);
        $this->jwtService = $this->createMock(JwtService::class);

        $this->loginUseCase = new LoginUseCase(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtService
        );
    }

    public function testSuccessfulLogin(): void
    {
        $dto = new LoginRequestDTO(
            email: 'test@example.com',
            password: 'password123'
        );

        $user = $this->createMock(User::class);
        $user->method('isActive')->willReturn(true);
        $user->method('getPassword')->willReturn('hashed_password');
        $user->method('getId')->willReturn(1);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('password123', 'hashed_password')
            ->willReturn(true);

        $this->jwtService
            ->expects($this->once())
            ->method('generateAccessToken')
            ->with($user->getId(), $user->getEmail())
            ->willReturn('access_token');
        $this->jwtService
            ->expects($this->once())
            ->method('generateRefreshToken')
            ->with($user->getId())
            ->willReturn('refresh_token');
        $this->jwtService->method('getAccessTokenExpire')->willReturn(3600);

        $result = $this->loginUseCase->execute($dto);

        $this->assertInstanceOf(LoginResponseDTO::class, $result);
        $this->assertEquals('access_token', $result->accessToken);
        $this->assertEquals('refresh_token', $result->refreshToken);
        $this->assertEquals(3600, $result->expiresIn);
        $this->assertEquals('Bearer', $result->tokenType);
    }

    public function testLoginWithInvalidEmailThrowsException(): void
    {
        $dto = new LoginRequestDTO(
            email: 'invalid@example.com',
            password: 'password123'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('invalid@example.com')
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Credenciais inválidas.');

        $this->loginUseCase->execute($dto);
    }

    public function testLoginWithWrongPasswordThrowsException(): void
    {
        $dto = new LoginRequestDTO(
            email: 'test@example.com',
            password: 'wrong_password'
        );

        $user = $this->createMock(User::class);
        $user->method('getPassword')->willReturn('hashed_password');
        $user->method('isActive')->willReturn(true);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('wrong_password', 'hashed_password')
            ->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Credenciais inválidas.');

        $this->loginUseCase->execute($dto);
    }

    public function testLoginWithInactiveUserThrowsException(): void
    {
        $dto = new LoginRequestDTO(
            email: 'test@example.com',
            password: 'password123'
        );

        $user = $this->createMock(User::class);
        $user->method('isActive')->willReturn(false);
        $user->method('getPassword')->willReturn('hashed_password');

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('password123', 'hashed_password')
            ->willReturn(true);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('A conta do usuário não está ativa.');

        $this->loginUseCase->execute($dto);
    }
}