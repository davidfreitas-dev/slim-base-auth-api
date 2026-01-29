<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function createPerson(array $data = []): Person
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'id' => 1,
        ];
        return new Person(...array_merge($defaults, $data));
    }

    private function createRole(array $data = []): Role
    {
        $defaults = [
            'id' => 1,
            'name' => 'user',
            'description' => 'User role',
            'createdAt' => new DateTimeImmutable(),
            'updatedAt' => new DateTimeImmutable(),
        ];
        return new Role(...array_merge($defaults, $data));
    }

    private function createUser(array $data = []): User
    {
        $person = $data['person'] ?? $this->createPerson();
        $role = $data['role'] ?? $this->createRole();

        $defaults = [
            'person' => $person,
            'role' => $role,
            'password' => 'password',
            'isActive' => true,
            'isVerified' => false,
            'createdAt' => new DateTimeImmutable(),
            'updatedAt' => new DateTimeImmutable(),
        ];

        return new User(...array_merge($defaults, $data));
    }


    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $person = $this->createPerson();
        $role = $this->createRole();
        $user = $this->createUser(['person' => $person, 'role' => $role]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertSame($person, $user->getPerson());
        $this->assertSame('password', $user->getPassword());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isVerified());
        $this->assertSame($role, $user->getRole());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertSame('john.doe@example.com', $user->getEmail());
    }

    public function testSetPasswordUpdatesPasswordAndTouches(): void
    {
        $user = $this->createUser();
        $initialUpdatedAt = $user->getUpdatedAt();
        sleep(1);
        $user->setPassword('new-password');

        $this->assertSame('new-password', $user->getPassword());
        $this->assertNotSame($initialUpdatedAt->getTimestamp(), $user->getUpdatedAt()->getTimestamp());
    }

    public function testActivateAndDeactivateWork(): void
    {
        $user = $this->createUser(['isActive' => false]);
        $this->assertFalse($user->isActive());

        $user->activate();
        $this->assertTrue($user->isActive());

        $user->deactivate();
        $this->assertFalse($user->isActive());
    }

    public function testMarkAsVerifiedAndUnverifiedWork(): void
    {
        $user = $this->createUser(['isVerified' => false]);
        $this->assertFalse($user->isVerified());

        $user->markAsVerified();
        $this->assertTrue($user->isVerified());

        $user->markAsUnverified();
        $this->assertFalse($user->isVerified());
    }

    public function testSetRoleUpdatesRoleAndTouches(): void
    {
        $user = $this->createUser();
        $initialUpdatedAt = $user->getUpdatedAt();
        $newRole = $this->createRole(['id' => 2, 'name' => 'admin']);
        sleep(1);
        $user->setRole($newRole);

        $this->assertSame($newRole, $user->getRole());
        $this->assertNotSame($initialUpdatedAt->getTimestamp(), $user->getUpdatedAt()->getTimestamp());
    }

    public function testToArrayAndJsonSerializeReturnCorrectArray(): void
    {
        $person = $this->createPerson([
            'phone' => '123456',
            'avatarUrl' => 'http://my.avatar'
        ]);
        $createdAt = new DateTimeImmutable('-1 day');
        $updatedAt = new DateTimeImmutable();
        $user = $this->createUser([
            'person' => $person,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ]);

        $personArray = $person->toArray();
        unset($personArray['id']);

        $expected = array_merge([
            'id' => $user->getId(),
            'role_id' => $user->getRole()->getId(),
            'role_name' => $user->getRole()->getName(),
            'is_active' => $user->isActive(),
            'is_verified' => $user->isVerified(),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ], $personArray);

        $this->assertEquals($expected, $user->toArray());
        $this->assertEquals($expected, $user->jsonSerialize());
    }

    public function testFromArrayCreatesUserInstance(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => null,
            'cpfcnpj' => null,
            'avatar_url' => null,
            'password' => 'hashed_password',
            'role_id' => 1,
            'role_name' => 'user',
            'is_active' => true,
            'is_verified' => false,
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];

        $user = User::fromArray($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['id'], $user->getId());
        $this->assertSame($data['name'], $user->getPerson()->getName());
        $this->assertSame($data['password'], $user->getPassword());
        $this->assertSame($data['role_id'], $user->getRole()->getId());
        $this->assertSame($data['role_name'], $user->getRole()->getName());
        $this->assertSame($data['is_active'], $user->isActive());
        $this->assertSame($data['is_verified'], $user->isVerified());
        $this->assertSame($data['created_at'], $user->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertSame($data['updated_at'], $user->getUpdatedAt()->format('Y-m-d H:i:s'));
    }
}