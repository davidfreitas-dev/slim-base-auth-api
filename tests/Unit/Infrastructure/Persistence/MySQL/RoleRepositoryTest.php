<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Role;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\RoleRepository
 */
final class RoleRepositoryTest extends TestCase
{
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

    private function getRoleData(int $id = 1): array
    {
        return [
            'id' => $id,
            'name' => 'Admin',
            'description' => 'Administrator role',
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];
    }

    public function testFindByIdFound(): void
    {
        $roleData = $this->getRoleData(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM roles WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($roleData);

        $repository = new RoleRepository($pdo);
        $result = $repository->findById(1);

        self::assertNotNull($result);
        self::assertEquals($this->createRole(1), $result);
    }

    public function testFindByIdNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $repository = new RoleRepository($pdo);
        $result = $repository->findById(999);

        self::assertNull($result);
    }

    public function testFindByNameFound(): void
    {
        $roleData = $this->getRoleData(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM roles WHERE name = :name'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['name' => 'Admin'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($roleData);

        $repository = new RoleRepository($pdo);
        $result = $repository->findByName('Admin');

        self::assertNotNull($result);
        self::assertEquals($this->createRole(1), $result);
    }

    public function testFindByNameNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $repository = new RoleRepository($pdo);
        $result = $repository->findByName('NonExistentRole');

        self::assertNull($result);
    }
}