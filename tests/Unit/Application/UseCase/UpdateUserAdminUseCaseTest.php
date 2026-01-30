<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use PDO;
use Faker\Factory;
use Tests\TestCase;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use App\Application\DTO\UserResponseDTO;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\DTO\UpdateUserAdminRequestDTO;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Application\UseCase\UpdateUserAdminUseCase;
use App\Domain\Repository\PersonRepositoryInterface;

class UpdateUserAdminUseCaseTest extends TestCase
{
    private PDO&MockObject $pdo;

    private UserRepositoryInterface&MockObject $userRepository;

    private PersonRepositoryInterface&MockObject $personRepository;

    private RoleRepositoryInterface&MockObject $roleRepository;

    private UpdateUserAdminUseCase $updateUserAdminUseCase;

    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');

        $this->updateUserAdminUseCase = new UpdateUserAdminUseCase(
            $this->pdo,
            $this->userRepository,
            $this->personRepository,
            $this->roleRepository
        );
    }

    public function testShouldUpdateUserSuccessfully(): void
    {
        $userId = 1;
        $newName = 'New Name';
        $newEmail = 'new@email.com';
        $newPhone = '11987654321';
        $newCpfCnpj = $this->faker->cpf();
        $newRoleName = 'NewRole';
        $newIsActive = true;
        $newIsVerified = true;

        $dto = new UpdateUserAdminRequestDTO(
            userId: $userId,
            name: $newName,
            email: $newEmail,
            phone: $newPhone,
            cpfcnpj: $newCpfCnpj,
            roleName: $newRoleName,
            isActive: $newIsActive,
            isVerified: $newIsVerified
        );

        /** @var Person&MockObject $mockPerson */
        $mockPerson = $this->createMock(Person::class);
        $mockPerson->method('getId')->willReturn($userId);
        $mockPerson->method('getName')->willReturn($newName);
        $mockPerson->method('getEmail')->willReturn($newEmail);
        $mockPerson->method('getPhone')->willReturn($newPhone);
        $mockPerson->method('getCpfCnpj')->willReturn(CpfCnpj::fromString($newCpfCnpj));
        $mockPerson->expects($this->once())->method('setName')->with($newName);
        $mockPerson->expects($this->once())->method('setEmail')->with($newEmail);
        $mockPerson->expects($this->once())->method('setPhone')->with($newPhone);
        $mockPerson->expects($this->once())->method('setCpfCnpj')->with(CpfCnpj::fromString($newCpfCnpj));

        /** @var Role&MockObject $mockRole */
        $mockRole = $this->createMock(Role::class);
        $mockRole->method('getName')->willReturn($newRoleName);
        $this->roleRepository->method('findByName')->with($newRoleName)->willReturn($mockRole);

        /** @var User&MockObject $mockUser */
        $mockUser = $this->createMock(User::class);
        $mockUser->method('getId')->willReturn($userId);
        $mockUser->method('getPerson')->willReturn($mockPerson);
        $mockUser->method('getRole')->willReturn($mockRole);
        $mockUser->method('isActive')->willReturn($newIsActive);
        $mockUser->method('isVerified')->willReturn($newIsVerified);
        $mockUser->expects($this->once())->method('setRole')->with($mockRole);
        $mockUser->expects($this->once())->method('activate');
        $mockUser->expects($this->once())->method('markAsVerified');

        $this->userRepository->method('findById')->with($userId)->willReturn($mockUser);
        $this->personRepository->method('findByEmail')->with($newEmail)->willReturn(null);
        $this->personRepository->method('findByCpfCnpj')->with($newCpfCnpj)->willReturn(null);
        $this->personRepository->expects($this->once())->method('update')->with($mockPerson);
        $this->userRepository->expects($this->once())->method('update')->with($mockUser)->willReturn($mockUser);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->once())->method('commit');

        $result = $this->updateUserAdminUseCase->execute($dto);
        $this->assertInstanceOf(UserResponseDTO::class, $result);
        $this->assertEquals($userId, $result->id);
        $this->assertEquals($newName, $result->name);
        $this->assertEquals($newEmail, $result->email);
        $this->assertEquals($newRoleName, $result->roleName);
        $this->assertEquals($newIsActive, $result->isActive);
        $this->assertEquals($newIsVerified, $result->isVerified);
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

        /** @var Person&MockObject $userPerson */
        $userPerson = $this->createMock(Person::class);
        $userPerson->method('getId')->willReturn(1);
        
        /** @var User&MockObject $userMock */
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($userPerson);
        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        /** @var Person&MockObject $conflictingPerson */
        $conflictingPerson = $this->createMock(Person::class);
        $conflictingPerson->method('getId')->willReturn(2);
        $this->personRepository->method('findByEmail')->with('conflict@email.com')->willReturn($conflictingPerson);

        $this->updateUserAdminUseCase->execute($dto);
    }
}