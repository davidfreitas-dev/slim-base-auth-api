<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\UpdateUserAdminRequestDTO;
use App\Application\UseCase\UpdateUserAdminUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ConflictException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateUserAdminUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $pdo;

    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $personRepository;

    private \PHPUnit\Framework\MockObject\MockObject $roleRepository;

    private UpdateUserAdminUseCase $updateUserAdminUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $this->updateUserAdminUseCase = new UpdateUserAdminUseCase(
            $this->pdo,
            $this->userRepository,
            $this->personRepository,
            $this->roleRepository
        );
    }

    public function testShouldUpdateUserSuccessfully(): void
    {
        $dto = new UpdateUserAdminRequestDTO(1, 'New Name', 'new@email.com', null, null, 'NewRole', true, true);

        $personMock = $this->createMock(Person::class);
        $personMock->method('getId')->willReturn(1);
        $personMock->expects($this->once())->method('setName')->with('New Name');
        $personMock->expects($this->once())->method('setEmail')->with('new@email.com');

        $roleMock = $this->createMock(Role::class);
        $this->roleRepository->method('findByName')->with('NewRole')->willReturn($roleMock);

        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($personMock);
        $userMock->expects($this->once())->method('setRole')->with($roleMock);
        $userMock->expects($this->once())->method('activate');
        $userMock->expects($this->once())->method('markAsVerified');

        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->once())->method('commit');

        $this->updateUserAdminUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionForNonExistentUser(): void
    {
        $this->expectException(NotFoundException::class);
        $dto = new UpdateUserAdminRequestDTO(999, 'name', 'email', null, null, 'Role', true, true);
        $this->userRepository->method('findById')->with(999)->willReturn(null);
        $this->updateUserAdminUseCase->execute($dto);
    }

    public function testShouldThrowConflictExceptionOnEmailConflict(): void
    {
        $this->expectException(ConflictException::class);
        $dto = new UpdateUserAdminRequestDTO(1, 'name', 'conflict@email.com', null, null, 'Role', true, true);

        $userPerson = $this->createMock(Person::class);
        $userPerson->method('getId')->willReturn(1);
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($userPerson);
        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        $conflictingPerson = $this->createMock(Person::class);
        $conflictingPerson->method('getId')->willReturn(2);
        $this->personRepository->method('findByEmail')->with('conflict@email.com')->willReturn($conflictingPerson);

        $this->updateUserAdminUseCase->execute($dto);
    }
}
