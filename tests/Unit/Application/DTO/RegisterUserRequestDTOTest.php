<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\RegisterUserRequestDTO;
use App\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RegisterUserRequestDTO::class)]
class RegisterUserRequestDTOTest extends TestCase
{
    public function testFromArrayWithValidData(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '123456789',
            'cpfcnpj' => '12345678901',
        ];

        $dto = RegisterUserRequestDTO::fromArray($data);

        $this->assertSame($data['name'], $dto->name);
        $this->assertSame($data['email'], $dto->email);
        $this->assertSame($data['password'], $dto->password);
        $this->assertSame($data['phone'], $dto->phone);
        $this->assertSame($data['cpfcnpj'], $dto->cpfcnpj);
    }

    public function testToArray(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '123456789',
            'cpfcnpj' => '12345678901',
        ];

        $dto = RegisterUserRequestDTO::fromArray($data);
        $dtoArray = $dto->toArray();

        $this->assertSame($data['name'], $dtoArray['name']);
        $this->assertSame($data['email'], $dtoArray['email']);
        $this->assertSame($data['password'], $dtoArray['password']);
        $this->assertSame($data['phone'], $dtoArray['phone']);
        $this->assertSame($data['cpfcnpj'], $dtoArray['cpfcnpj']);
    }
}
