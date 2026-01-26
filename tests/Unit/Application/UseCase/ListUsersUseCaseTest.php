<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\UseCase\ListUsersUseCase;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ListUsersUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private ListUsersUseCase $listUsersUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->listUsersUseCase = new ListUsersUseCase($this->userRepository);
    }

    public function testShouldReturnArrayOfUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        $users = [$user1, $user2];
        $limit = 10;
        $offset = 0;

        $this->userRepository->expects($this->once())
            ->method('findAll')
            ->with($limit, $offset)
            ->willReturn($users);

        $result = $this->listUsersUseCase->execute($limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($users, $result);
    }

    public function testShouldReturnEmptyArrayWhenNoUsersFound(): void
    {
        $limit = 10;
        $offset = 0;

        $this->userRepository->expects($this->once())
            ->method('findAll')
            ->with($limit, $offset)
            ->willReturn([]);

        $result = $this->listUsersUseCase->execute($limit, $offset);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
