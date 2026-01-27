<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\UserListResponseDTO;
use App\Application\DTO\UserResponseDTO;
use App\Application\UseCase\ListUsersUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ListUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    
    private ListUsersUseCase $listUsersUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->listUsersUseCase = new ListUsersUseCase($this->userRepository);
    }

    public function testShouldReturnUserListResponseDTOWithUsers(): void
    {
        // Mock User entities
        /** @var User&MockObject $userEntity1 */
        $userEntity1 = $this->createMock(User::class);
        $userEntity1->method('getId')->willReturn(1);
        $userEntity1->method('getPerson')->willReturn($this->createMockPerson('User 1', 'user1@example.com'));
        $userEntity1->method('getRole')->willReturn($this->createMockRole('user'));
        $userEntity1->method('isActive')->willReturn(true);
        $userEntity1->method('isVerified')->willReturn(true);

        /** @var User&MockObject $userEntity2 */
        $userEntity2 = $this->createMock(User::class);
        $userEntity2->method('getId')->willReturn(2);
        $userEntity2->method('getPerson')->willReturn($this->createMockPerson('User 2', 'user2@example.com'));
        $userEntity2->method('getRole')->willReturn($this->createMockRole('admin'));
        $userEntity2->method('isActive')->willReturn(true);
        $userEntity2->method('isVerified')->willReturn(false);

        $users = [$userEntity1, $userEntity2];
        $totalUsers = 2;
        $limit = 10;
        $offset = 0;

        $this->userRepository->expects($this->once())
            ->method('findAll')
            ->with($limit, $offset)
            ->willReturn($users);

        $this->userRepository->expects($this->once())
            ->method('count')
            ->willReturn($totalUsers);

        $result = $this->listUsersUseCase->execute($limit, $offset);

        $this->assertInstanceOf(UserListResponseDTO::class, $result);
        $this->assertCount(2, $result->users);
        $this->assertEquals($totalUsers, $result->total);
        $this->assertEquals($limit, $result->limit);
        $this->assertEquals($offset, $result->offset);

        // Assert properties of individual UserResponseDTOs
        $this->assertInstanceOf(UserResponseDTO::class, $result->users[0]);
        $this->assertEquals(1, $result->users[0]->id);
        $this->assertEquals('User 1', $result->users[0]->name);
        $this->assertEquals('user1@example.com', $result->users[0]->email);
        $this->assertEquals('user', $result->users[0]->roleName);
        $this->assertTrue($result->users[0]->isActive);
        $this->assertTrue($result->users[0]->isVerified);

        $this->assertInstanceOf(UserResponseDTO::class, $result->users[1]);
        $this->assertEquals(2, $result->users[1]->id);
        $this->assertEquals('User 2', $result->users[1]->name);
        $this->assertEquals('user2@example.com', $result->users[1]->email);
        $this->assertEquals('admin', $result->users[1]->roleName);
        $this->assertTrue($result->users[1]->isActive);
        $this->assertFalse($result->users[1]->isVerified);
    }

    public function testShouldReturnEmptyUserListResponseDTOWhenNoUsersFound(): void
    {
        $totalUsers = 0;
        $limit = 10;
        $offset = 0;

        $this->userRepository->expects($this->once())
            ->method('findAll')
            ->with($limit, $offset)
            ->willReturn([]);

        $this->userRepository->expects($this->once())
            ->method('count')
            ->willReturn($totalUsers);

        $result = $this->listUsersUseCase->execute($limit, $offset);

        $this->assertInstanceOf(UserListResponseDTO::class, $result);
        $this->assertEmpty($result->users);
        $this->assertEquals($totalUsers, $result->total);
        $this->assertEquals($limit, $result->limit);
        $this->assertEquals($offset, $result->offset);
    }

    /**
     * @return Person&MockObject
     */
    private function createMockPerson(string $name, string $email): Person&MockObject
    {
        $mockPerson = $this->createMock(Person::class);
        $mockPerson->method('getName')->willReturn($name);
        $mockPerson->method('getEmail')->willReturn($email);
        $mockPerson->method('getPhone')->willReturn(null);
        $mockPerson->method('getCpfCnpj')->willReturn(null);
        return $mockPerson;
    }

    /**
     * @return Role&MockObject
     */
    private function createMockRole(string $name): Role&MockObject
    {
        $mockRole = $this->createMock(Role::class);
        $mockRole->method('getName')->willReturn($name);
        return $mockRole;
    }
}