<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use PDO;
use Tests\TestCase;
use App\Application\DTO\CreateUserAdminRequestDTO;
use App\Application\DTO\UserResponseDTO;
use App\Application\UseCase\CreateUserAdminUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\MockObject\MockObject;

class CreateUserAdminUseCaseTest extends TestCase
{
    private PDO&MockObject $pdo;
    private PersonRepositoryInterface&MockObject $personRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private RoleRepositoryInterface&MockObject $roleRepository;
    private PasswordHasher&MockObject $passwordHasher;
    private CreateUserAdminUseCase $createUserAdminUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasher::class);

        $this->createUserAdminUseCase = new CreateUserAdminUseCase(
            $this->pdo,
            $this->personRepository,
            $this->userRepository,
            $this->roleRepository,
            $this->passwordHasher
        );
    }

    public function testShouldCreateUserSuccessfully(): void
    {
        $dto = new CreateUserAdminRequestDTO('Admin User', 'admin@test.com', 'password', null, null, 'Admin');

        $userId = 1;
        $userName = $dto->name;
        $userEmail = $dto->email;
        $userRoleName = $dto->roleName;
        $userIsActive = true;
        $userIsVerified = true;

        $this->personRepository->method('findByEmail')->willReturn(null);

        /** @var Role&MockObject $mockRole */
        $mockRole = $this->createMock(Role::class);
        $mockRole->method('getName')->willReturn($userRoleName);
        $this->roleRepository->method('findByName')->with($userRoleName)->willReturn($mockRole);

        /** @var Person&MockObject $mockPerson */
        $mockPerson = $this->createMock(Person::class);
        $mockPerson->method('getId')->willReturn($userId);
        $mockPerson->method('getName')->willReturn($userName);
        $mockPerson->method('getEmail')->willReturn($userEmail);
        $this->personRepository->method('create')->willReturn($mockPerson);

        /** @var User&MockObject $mockUser */
        $mockUser = $this->createMock(User::class);
        $mockUser->method('getId')->willReturn($userId);
        $mockUser->method('getPerson')->willReturn($mockPerson);
        $mockUser->method('getRole')->willReturn($mockRole);
        $mockUser->method('isActive')->willReturn($userIsActive);
        $mockUser->method('isVerified')->willReturn($userIsVerified);
        $this->userRepository->method('create')->willReturn($mockUser);

        $this->passwordHasher->expects($this->once())->method('hash')->willReturn('hashed_password');

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->once())->method('commit');

        $result = $this->createUserAdminUseCase->execute($dto);
        $this->assertInstanceOf(UserResponseDTO::class, $result);
        $this->assertEquals($userId, $result->id);
        $this->assertEquals($userName, $result->name);
        $this->assertEquals($userEmail, $result->email);
        $this->assertEquals($userRoleName, $result->roleName);
        $this->assertEquals($userIsActive, $result->isActive);
        $this->assertEquals($userIsVerified, $result->isVerified);
    }

    public function testShouldThrowConflictExceptionOnEmailInUse(): void
    {
        $this->expectException(ConflictException::class);
        $dto = new CreateUserAdminRequestDTO('User', 'used@email.com', 'pass', null, null, 'User');
        $this->personRepository->method('findByEmail')->willReturn($this->createMock(Person::class));
        $this->createUserAdminUseCase->execute($dto);
    }

    public function testShouldThrowNotFoundExceptionForInvalidRole(): void
    {
        $this->expectException(NotFoundException::class);
        $dto = new CreateUserAdminRequestDTO('User', 'new@email.com', 'pass', null, null, 'InvalidRole');
        $this->personRepository->method('findByEmail')->willReturn(null);
        $this->roleRepository->method('findByName')->with('InvalidRole')->willReturn(null);
        $this->createUserAdminUseCase->execute($dto);
    }

    public function testShouldRollbackOnFailure(): void
    {
        $this->expectException(\Exception::class);
        $dto = new CreateUserAdminRequestDTO('Admin User', 'admin@test.com', 'password', null, null, 'Admin');

        /** @var Role&MockObject $role */
        $role = $this->createMock(Role::class);
        $this->roleRepository->method('findByName')->willReturn($role);

        $this->personRepository->method('create')->will($this->throwException(new \Exception('DB Error')));

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack');

        $this->createUserAdminUseCase->execute($dto);
    }
}