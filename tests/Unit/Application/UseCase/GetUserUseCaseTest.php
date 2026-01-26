<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\UseCase\GetUserUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class GetUserUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

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
        $user = $this->createMock(User::class);

        $this->userRepository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $result = $this->getUserUseCase->execute($userId);

        $this->assertSame($user, $result);
    }

    public function testShouldThrowNotFoundExceptionWhenUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User not found.');

        $userId = 999;
        $this->userRepository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->getUserUseCase->execute($userId);
    }
}
