<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\UserRepository
 */
final class UserRepositoryTest extends TestCase
{
    private function createPerson(int $id = 1): Person
    {
        return new Person(
            name: 'John Doe',
            email: 'john.doe@example.com',
            phone: '11987654321',
            cpfcnpj: CpfCnpj::fromString('12345678909'),
            avatarUrl: 'https://example.com/avatar.jpg',
            id: $id,
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2023-01-01 10:00:00'),
        );
    }

    private function createRole(int $id = 1): Role
    {
        return new Role(
            id: $id,
            name: 'Admin',
            description: 'Administrator role',
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2023-01-01 10:00:00'),
        );
    }

    private function createUser(int $id = 1): User
    {
        return new User(
            person: $this->createPerson($id),
            password: 'hashed_password',
            role: $this->createRole(),
            isActive: true,
            isVerified: true,
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2023-01-01 10:00:00'),
        );
    }

    private function getUserData(int $id = 1): array
    {
        return [
            // User data
            'id' => $id,
            'password' => 'hashed_password',
            'is_active' => 1,
            'is_verified' => 1,
            'user_created_at' => '2023-01-01 10:00:00',
            'user_updated_at' => '2023-01-01 10:00:00',
            // Role data
            'role_id' => 1,
            'role_name' => 'Admin',
            'role_description' => 'Administrator role',
            'role_created_at' => '2023-01-01 10:00:00',
            'role_updated_at' => '2023-01-01 10:00:00',
            // Person data
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '11987654321',
            'cpfcnpj' => '12345678909',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];
    }

    public function testCreate(): void
    {
        $user = $this->createUser();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO users (id, password, is_active, is_verified, role_id, created_at, updated_at)'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'id' => $user->getId(),
                'password' => $user->getPassword(),
                'is_active' => (int)$user->isActive(),
                'is_verified' => (int)$user->isVerified(),
                'role_id' => $user->getRole()->getId(),
            ])
            ->willReturn(true);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->create($user);

        self::assertEquals($user, $result);
    }

    public function testFindByIdFound(): void
    {
        $userData = $this->getUserData(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at,'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($userData);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findById(1);

        self::assertNotNull($result);
        self::assertEquals($this->createUser(1)->toArray(), $result->toArray());
    }

    public function testFindByIdNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findById(999);

        self::assertNull($result);
    }

    public function testFindByEmailFound(): void
    {
        $userData = $this->getUserData(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE p.email = :email'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['email' => 'john.doe@example.com'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($userData);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findByEmail('john.doe@example.com');

        self::assertNotNull($result);
        self::assertEquals($this->createUser(1)->toArray(), $result->toArray());
    }

    public function testFindByEmailNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findByEmail('nonexistent@example.com');

        self::assertNull($result);
    }

    public function testFindByCpfCnpjFound(): void
    {
        $userData = $this->getUserData(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE p.cpfcnpj = :cpfcnpj'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['cpfcnpj' => '12345678909'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($userData);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findByCpfCnpj('12345678909');

        self::assertNotNull($result);
        self::assertEquals($this->createUser(1)->toArray(), $result->toArray());
    }

    public function testFindByCpfCnpjNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findByCpfCnpj('99988877766');

        self::assertNull($result);
    }

    public function testFindAll(): void
    {
        $user1Data = $this->getUserData(1);
        $user2Data = $this->getUserData(2);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at,'))
            ->willReturn($stmt);

        $stmt->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($user1Data, $user2Data, false);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->findAll();

        self::assertCount(2, $result);
        self::assertEquals($this->createUser(1)->toArray(), $result[0]->toArray());
        self::assertEquals($this->createUser(2)->toArray(), $result[1]->toArray());
    }

    public function testUpdate(): void
    {
        $user = $this->createUser();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $personRepository->expects($this->once())
            ->method('update')
            ->with($user->getPerson())
            ->willReturn($user->getPerson());

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users 
                SET password = :password, is_active = :is_active, is_verified = :is_verified, role_id = :role_id, updated_at = NOW()
                WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'id' => $user->getId(),
                'password' => $user->getPassword(),
                'is_active' => (int)$user->isActive(),
                'is_verified' => (int)$user->isVerified(),
                'role_id' => $user->getRole()->getId(),
            ])
            ->willReturn(true);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->update($user);

        self::assertEquals($user, $result);
    }

    public function testDelete(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM users WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->delete(1);

        self::assertTrue($result);
    }

    public function testDeleteNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->delete(999);

        self::assertFalse($result);
    }

    public function testCount(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('query')
            ->with($this->stringContains('SELECT COUNT(*) as total FROM users'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['total' => 5]);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $result = $userRepository->count();

        self::assertEquals(5, $result);
    }

    public function testUpdatePassword(): void
    {
        $user = $this->createUser();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'id' => $user->getId(),
                'password' => 'new_hashed_password',
            ])
            ->willReturn(true);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $userRepository->updatePassword($user->getId(), 'new_hashed_password');
        
        self::assertTrue(true);
    }

    public function testMarkUserAsVerified(): void
    {
        $user = $this->createUser();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $personRepository = $this->createMock(PersonRepositoryInterface::class);
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET is_verified = TRUE, updated_at = NOW() WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => $user->getId()])
            ->willReturn(true);

        $userRepository = new UserRepository($pdo, $personRepository, $roleRepository);
        $userRepository->markUserAsVerified($user->getId());
        
        self::assertTrue(true);
    }
}