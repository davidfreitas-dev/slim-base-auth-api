<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\CreateUserAdminRequestDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateUserAdminRequestDTO::class)]
class CreateUserAdminRequestDTOTest extends TestCase
{
    public function testFromArrayWithValidDataAndDefaultRole(): void
    {
        $data = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'phone' => '123456789',
            'cpfcnpj' => '12345678901',
        ];

        $dto = CreateUserAdminRequestDTO::fromArray($data);

        $this->assertSame('Admin User', $dto->name);
        $this->assertSame('admin@example.com', $dto->email);
        $this->assertSame('password123', $dto->password);
        $this->assertSame('123456789', $dto->phone);
        $this->assertSame('12345678901', $dto->cpfcnpj);
        $this->assertSame('user', $dto->roleName);
    }

    public function testFromArrayWithValidDataAndSpecificRole(): void
    {
        $data = [
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'superpassword',
            'phone' => '987654321',
            'cpfcnpj' => '09876543210',
            'role' => 'admin',
        ];

        $dto = CreateUserAdminRequestDTO::fromArray($data);

        $this->assertSame('Super Admin', $dto->name);
        $this->assertSame('superadmin@example.com', $dto->email);
        $this->assertSame('superpassword', $dto->password);
        $this->assertSame('987654321', $dto->phone);
        $this->assertSame('09876543210', $dto->cpfcnpj);
        $this->assertSame('admin', $dto->roleName);
    }

    public function testToArray(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'phone' => '111222333',
            'cpfcnpj' => '11122233344',
            'role' => 'moderator',
        ];

        $dto = CreateUserAdminRequestDTO::fromArray($data);
        $dtoArray = $dto->toArray();

        $this->assertSame($data['name'], $dtoArray['name']);
        $this->assertSame($data['email'], $dtoArray['email']);
        $this->assertSame($data['password'], $dtoArray['password']);
        $this->assertSame($data['phone'], $dtoArray['phone']);
        $this->assertSame($data['cpfcnpj'], $dtoArray['cpfcnpj']);
        $this->assertSame($data['role'], $dtoArray['role']);
    }

    public function testToArrayWithDefaultRole(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'phone' => '111222333',
            'cpfcnpj' => '11122233344',
        ];

        $dto = CreateUserAdminRequestDTO::fromArray($data);
        $dtoArray = $dto->toArray();

        $this->assertSame($data['name'], $dtoArray['name']);
        $this->assertSame($data['email'], $dtoArray['email']);
        $this->assertSame($data['password'], $dtoArray['password']);
        $this->assertSame($data['phone'], $dtoArray['phone']);
        $this->assertSame($data['cpfcnpj'], $dtoArray['cpfcnpj']);
        $this->assertSame('user', $dtoArray['role']);
    }
}
