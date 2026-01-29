<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PersonTest extends TestCase
{
    private function createPerson(array $data = []): Person
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => null,
            'cpfcnpj' => null,
            'avatarUrl' => null,
            'id' => null,
            'createdAt' => new DateTimeImmutable(),
            'updatedAt' => new DateTimeImmutable(),
        ];

        return new Person(...array_merge($defaults, $data));
    }

    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $cpfCnpj = CpfCnpj::fromString('11144477735');
        $person = $this->createPerson([
            'id' => 1,
            'cpfcnpj' => $cpfCnpj,
            'phone' => '123456789',
            'avatarUrl' => 'http://example.com/avatar.jpg',
        ]);

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame(1, $person->getId());
        $this->assertSame('John Doe', $person->getName());
        $this->assertSame('john.doe@example.com', $person->getEmail());
        $this->assertSame('123456789', $person->getPhone());
        $this->assertSame($cpfCnpj, $person->getCpfCnpj());
        $this->assertSame('11144477735', $person->getCpfCnpj()?->value());
        $this->assertSame('http://example.com/avatar.jpg', $person->getAvatarUrl());
        $this->assertInstanceOf(DateTimeImmutable::class, $person->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $person->getUpdatedAt());
    }

    public function testSettersWorkCorrectly(): void
    {
        $person = $this->createPerson();

        $person->setId(2);
        $person->setName('Jane Roe');
        $person->setEmail('jane.roe@example.com');
        $person->setPhone('987654321');
        $cpfCnpj = CpfCnpj::fromString('12345678909');
        $person->setCpfCnpj($cpfCnpj);
        $person->setAvatarUrl('http://example.com/new-avatar.jpg');

        $this->assertSame(2, $person->getId());
        $this->assertSame('Jane Roe', $person->getName());
        $this->assertSame('jane.roe@example.com', $person->getEmail());
        $this->assertSame('987654321', $person->getPhone());
        $this->assertSame($cpfCnpj, $person->getCpfCnpj());
        $this->assertSame('http://example.com/new-avatar.jpg', $person->getAvatarUrl());
    }

    public function testTouchMethodUpdatesUpdatedAt(): void
    {
        $person = $this->createPerson();
        $initialUpdatedAt = $person->getUpdatedAt();
        sleep(1);
        $person->touch();
        $this->assertNotSame($initialUpdatedAt->getTimestamp(), $person->getUpdatedAt()->getTimestamp());
    }

    public function testToArrayAndJsonSerializeReturnCorrectArray(): void
    {
        $cpfCnpj = CpfCnpj::fromString('11144477735');
        $createdAt = new DateTimeImmutable('-1 day');
        $updatedAt = new DateTimeImmutable();

        $person = $this->createPerson([
            'id' => 1,
            'cpfcnpj' => $cpfCnpj,
            'phone' => '123456789',
            'avatarUrl' => 'http://avatar.url',
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ]);

        $expectedArray = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456789',
            'cpfcnpj' => '11144477735',
            'avatar_url' => 'http://avatar.url',
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ];

        $this->assertEquals($expectedArray, $person->toArray());
        $this->assertEquals($expectedArray, $person->jsonSerialize());
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123456789',
            'cpfcnpj' => '11144477735',
            'avatar_url' => 'http://example.com/avatar.jpg',
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-02 12:00:00',
        ];

        $person = Person::fromArray($data);

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame(1, $person->getId());
        $this->assertSame('John Doe', $person->getName());
        $this->assertSame('11144477735', $person->getCpfCnpj()?->value());
        $this->assertSame('2023-01-01 12:00:00', $person->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2023-01-02 12:00:00', $person->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    public function testFromArrayWithCpfCnpjObject(): void
    {
        $cpfCnpj = CpfCnpj::fromString('11144477735');
        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'cpfcnpj' => $cpfCnpj,
        ];

        $person = Person::fromArray($data);
        $this->assertSame($cpfCnpj, $person->getCpfCnpj());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'name' => 'Minimal Person',
            'email' => 'minimal@example.com',
        ];

        $person = Person::fromArray($data);

        $this->assertSame('Minimal Person', $person->getName());
        $this->assertSame('minimal@example.com', $person->getEmail());
        $this->assertNull($person->getId());
        $this->assertNull($person->getPhone());
        $this->assertNull($person->getCpfCnpj());
        $this->assertNull($person->getAvatarUrl());
        $this->assertNull($person->getCreatedAt());
        $this->assertNull($person->getUpdatedAt());
    }
}