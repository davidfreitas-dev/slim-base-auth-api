<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\UserResponseDTO;
use App\Application\UseCase\GetUserUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class GetUserUseCaseTest extends TestCase
{
    private MockObject $userRepository;

    private GetUserUseCase $getUserUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->getUserUseCase = new GetUserUseCase($this->userRepository);
    }

    public function testShouldReturnUserWhenFound(): void
    {
        $userId = 1;
        $mockUserName = 'Test User';
        $mockUserEmail = 'test@example.com';
        $mockRoleName = 'user';
        $mockIsActive = true;
        $mockIsVerified = false;

        $mockPerson = $this->createMock(Person::class);
        $mockPerson->method('getName')->willReturn($mockUserName);
        $mockPerson->method('getEmail')->willReturn($mockUserEmail);

        $mockRole = $this->createMock(Role::class);
        $mockRole->method('getName')->willReturn($mockRoleName);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getPerson')->willReturn($mockPerson);
        $user->method('getRole')->willReturn($mockRole);
        $user->method('isActive')->willReturn($mockIsActive);
        $user->method('isVerified')->willReturn($mockIsVerified);

        $this->userRepository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $result = $this->getUserUseCase->execute($userId);

        $this->assertInstanceOf(UserResponseDTO::class, $result);
        $this->assertEquals($userId, $result->id);
        $this->assertEquals($mockUserName, $result->name);
        $this->assertEquals($mockUserEmail, $result->email);
        $this->assertEquals($mockRoleName, $result->roleName);
        $this->assertEquals($mockIsActive, $result->isActive);
        $this->assertEquals($mockIsVerified, $result->isVerified);
    }

    public function testShouldThrowNotFoundExceptionWhenUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Usuário não encontrado.');

        $userId = 999;
        $this->userRepository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->getUserUseCase->execute($userId);
    }
}
