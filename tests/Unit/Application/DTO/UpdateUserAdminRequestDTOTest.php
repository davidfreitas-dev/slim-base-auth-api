<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\UpdateUserAdminRequestDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UpdateUserAdminRequestDTO::class)]
class UpdateUserAdminRequestDTOTest extends TestCase
{
    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'cpfcnpj' => '12345678901',
            'role' => 'admin',
            'is_active' => true,
            'is_verified' => true,
        ];
        $userId = 1;

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $data);

        $this->assertSame($userId, $dto->userId);
        $this->assertSame($data['name'], $dto->name);
        $this->assertSame($data['email'], $dto->email);
        $this->assertSame($data['phone'], $dto->phone);
        $this->assertSame($data['cpfcnpj'], $dto->cpfcnpj);
        $this->assertSame($data['role'], $dto->roleName);
        $this->assertSame($data['is_active'], $dto->isActive);
        $this->assertSame($data['is_verified'], $dto->isVerified);
    }

    public function testFromArrayWithSomeFields(): void
    {
        $data = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ];
        $userId = 1;

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $data);

        $this->assertSame($userId, $dto->userId);
        $this->assertSame($data['name'], $dto->name);
        $this->assertSame($data['email'], $dto->email);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->cpfcnpj);
        $this->assertNull($dto->roleName);
        $this->assertNull($dto->isActive);
        $this->assertNull($dto->isVerified);
    }

    public function testToArray(): void
    {
        $data = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'cpfcnpj' => '12345678901',
            'role' => 'admin',
            'is_active' => true,
            'is_verified' => true,
        ];
        $userId = 1;

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $data);
        $dtoArray = $dto->toArray();

        $this->assertSame($data['name'], $dtoArray['name']);
        $this->assertSame($data['email'], $dtoArray['email']);
        $this->assertSame($data['phone'], $dtoArray['phone']);
        $this->assertSame($data['cpfcnpj'], $dtoArray['cpfcnpj']);
        $this->assertSame($data['role'], $dtoArray['role']);
        $this->assertSame($data['is_active'], $dtoArray['is_active']);
        $this->assertSame($data['is_verified'], $dtoArray['is_verified']);
    }
}
