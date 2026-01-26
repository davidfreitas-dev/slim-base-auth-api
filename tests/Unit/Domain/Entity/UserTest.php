<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use DateTimeImmutable;
use Tests\TestCase;

class UserTest extends TestCase
{
    private Person $person;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->person = new Person(
            name: 'John Doe',
            email: 'john.doe@example.com',
            id: 1
        );
        $this->role = new Role(
            id: 1,
            name: 'user',
            description: 'User role',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable()
        );
    }

    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $user = new User(
            person: $this->person,
            role: $this->role,
            password: 'password'
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertSame($this->person, $user->getPerson());
        $this->assertSame('password', $user->getPassword());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isVerified());
        $this->assertSame($this->role, $user->getRole());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertSame('john.doe@example.com', $user->getEmail());
    }

    public function testSetPasswordUpdatesPasswordAndUpdatedAt(): void
    {
        $user = new User($this->person, $this->role, 'password');
        $initialUpdatedAt = $user->getUpdatedAt();
        sleep(1);
        $user->setPassword('new-password');

        $this->assertSame('new-password', $user->getPassword());
        $this->assertNotEquals($initialUpdatedAt, $user->getUpdatedAt());
    }

    public function testActivateAndDeactivateWork(): void
    {
        $user = new User($this->person, $this->role, 'password', isActive: false);
        $this->assertFalse($user->isActive());

        $user->activate();
        $this->assertTrue($user->isActive());

        $user->deactivate();
        $this->assertFalse($user->isActive());
    }

            public function testMarkAsVerifiedAndUnverifiedWork(): void
            {
                $user = new User($this->person, $this->role, 'password');        $this->assertFalse($user->isVerified());

        $user->markAsVerified();
        $this->assertTrue($user->isVerified());

        $user->markAsUnverified();
        $this->assertFalse($user->isVerified());
    }

    public function testSetRoleUpdatesRole(): void
    {
        $user = new User($this->person, $this->role, 'password');
        $newRole = new Role(2, 'admin', 'Admin', new DateTimeImmutable(), new DateTimeImmutable());
        $user->setRole($newRole);

        $this->assertSame($newRole, $user->getRole());
    }

    public function testToArrayReturnsCorrectArray(): void
    {
        $user = new User($this->person, $this->role, 'password');
        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertSame($this->person->getId(), $array['id']);
        $this->assertSame($this->person->getName(), $array['name']);
        $this->assertSame($this->role->getId(), $array['role_id']);
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
    }
}
