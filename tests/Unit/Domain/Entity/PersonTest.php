<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use Tests\TestCase;

class PersonTest extends TestCase
{
    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $cpfCnpj = CpfCnpj::fromString('111.444.777-35');
        $person = new Person(
            name: 'John Doe',
            email: 'john.doe@example.com',
            phone: '123456789',
            cpfcnpj: $cpfCnpj,
            avatarUrl: 'http://example.com/avatar.jpg',
            id: 1
        );

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame(1, $person->getId());
        $this->assertSame('John Doe', $person->getName());
        $this->assertSame('john.doe@example.com', $person->getEmail());
        $this->assertSame('123456789', $person->getPhone());
        $this->assertSame($cpfCnpj->value(), $person->getCpfCnpj()?->value());
        $this->assertSame('http://example.com/avatar.jpg', $person->getAvatarUrl());
        $this->assertInstanceOf(DateTimeImmutable::class, $person->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $person->getUpdatedAt());
    }

    public function testSettersWorkCorrectly(): void
    {
        $person = new Person(name: 'Jane Doe', email: 'jane.doe@example.com');

        $person->setId(2);
        $person->setName('Jane Roe');
        $person->setEmail('jane.roe@example.com');
        $person->setPhone('987654321');
        $person->setCpfCnpj(CpfCnpj::fromString('123.456.789-09'));
        $person->setAvatarUrl('http://example.com/new-avatar.jpg');

        $this->assertSame(2, $person->getId());
        $this->assertSame('Jane Roe', $person->getName());
        $this->assertSame('jane.roe@example.com', $person->getEmail());
        $this->assertSame('987654321', $person->getPhone());
        $this->assertSame('12345678909', $person->getCpfCnpj()?->value());
        $this->assertSame('http://example.com/new-avatar.jpg', $person->getAvatarUrl());
    }

    public function testTouchMethodUpdatesUpdatedAt(): void
    {
        $person = new Person(name: 'Test', email: 'test@test.com');
        $initialUpdatedAt = $person->getUpdatedAt();
        sleep(1);
        $person->touch();
        $this->assertNotEquals($initialUpdatedAt, $person->getUpdatedAt());
    }

    public function testToArrayReturnsCorrectArrayRepresentation(): void
    {
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        $person = new Person(
            name: 'John Doe',
            email: 'john.doe@example.com',
            id: 1,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $expectedArray = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => null,
            'cpfcnpj' => null,
            'avatar_url' => null,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ];

        $this->assertEquals($expectedArray, $person->toArray());
    }

    public function testJsonSerializeReturnsCorrectArray(): void
    {
        $person = new Person(name: 'John Doe', email: 'john.doe@example.com', id: 1);
        $this->assertEquals($person->toArray(), $person->jsonSerialize());
    }

    public function testFromArrayCreatesPersonInstanceCorrectly(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456789',
            'cpfcnpj' => '000.000.001-91',
            'avatar_url' => 'http://example.com/avatar.jpg',
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-01 12:00:00',
        ];

        $person = Person::fromArray($data);

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame(1, $person->getId());
        $this->assertSame('John Doe', $person->getName());
        $this->assertSame('john.doe@example.com', $person->getEmail());
        $this->assertSame('123456789', $person->getPhone());
    }
}
