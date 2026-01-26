<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Role;
use DateTimeImmutable;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $id = 1;
        $name = 'admin';
        $description = 'Administrator role';
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        $role = new Role($id, $name, $description, $createdAt, $updatedAt);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame($id, $role->getId());
        $this->assertSame($name, $role->getName());
        $this->assertSame($description, $role->getDescription());
        $this->assertSame($createdAt, $role->getCreatedAt());
        $this->assertSame($updatedAt, $role->getUpdatedAt());
    }

    public function testToArrayReturnsCorrectArrayRepresentation(): void
    {
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        $role = new Role(1, 'admin', 'Admin role', $createdAt, $updatedAt);

        $expectedArray = [
            'id' => 1,
            'name' => 'admin',
            'description' => 'Admin role',
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ];

        $this->assertEquals($expectedArray, $role->toArray());
    }

    public function testJsonSerializeReturnsCorrectArray(): void
    {
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        $role = new Role(1, 'admin', 'Admin role', $createdAt, $updatedAt);

        $this->assertEquals($role->toArray(), $role->jsonSerialize());
    }
}
