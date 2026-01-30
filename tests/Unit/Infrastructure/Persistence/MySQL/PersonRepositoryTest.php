<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\PersonRepository
 */
final class PersonRepositoryTest extends TestCase
{
    private function createPerson(?int $id = null, ?string $cpfcnpj = null): Person
    {
        return new Person(
            name: 'John Doe',
            email: 'john.doe@example.com',
            phone: '(11) 98765-4321',
            cpfcnpj: $cpfcnpj ? CpfCnpj::fromString($cpfcnpj) : null,
            avatarUrl: 'https://example.com/avatar.jpg',
            id: $id,
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2023-01-01 10:00:00'),
        );
    }

    private function getPersonData(int $id = 1, ?string $cpfcnpj = null): array
    {
        return [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '11987654321',
            'cpfcnpj' => $cpfcnpj,
            'avatar_url' => 'https://example.com/avatar.jpg',
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];
    }

    public function testCreatePerson(): void
    {
        $person = $this->createPerson();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO persons (name, email, phone, cpfcnpj, avatar_url, created_at, updated_at)'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) {
                self::assertArrayHasKey('name', $params);
                self::assertSame('John Doe', $params['name']);
                self::assertArrayHasKey('email', $params);
                self::assertSame('john.doe@example.com', $params['email']);
                self::assertArrayHasKey('phone', $params);
                self::assertSame('11987654321', $params['phone']); // Sanitized
                self::assertArrayHasKey('cpfcnpj', $params);
                self::assertNull($params['cpfcnpj']); // No CPF/CNPJ initially
                self::assertArrayHasKey('avatar_url', $params);
                self::assertSame('https://example.com/avatar.jpg', $params['avatar_url']);
                return true;
            }))
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1');

        $repository = new PersonRepository($pdo);
        $result = $repository->create($person);

        self::assertEquals(1, $result->getId());
        self::assertSame('11987654321', $result->getPhone());
        self::assertNull($result->getCpfCnpj());
        self::assertEquals($person, $result);
    }

    public function testCreatePersonWithCpfCnpj(): void
    {
        $person = $this->createPerson(null, '12345678909'); // Valid CPF
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) {
                self::assertSame('12345678909', $params['cpfcnpj']); // Sanitized
                return true;
            }))
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1');

        $repository = new PersonRepository($pdo);
        $result = $repository->create($person);

        self::assertEquals(1, $result->getId());
        self::assertEquals('12345678909', $result->getCpfCnpj()?->value());
    }

    public function testFindByIdFound(): void
    {
        $personData = $this->getPersonData(1, '12345678909'); // Valid CPF
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM persons WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($personData);

        $repository = new PersonRepository($pdo);
        $result = $repository->findById(1);

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
        self::assertSame('John Doe', $result->getName());
        self::assertSame('john.doe@example.com', $result->getEmail());
        self::assertSame('11987654321', $result->getPhone());
        self::assertEquals('12345678909', $result->getCpfCnpj()?->value());
        self::assertSame('https://example.com/avatar.jpg', $result->getAvatarUrl());
        self::assertEquals(new DateTimeImmutable('2023-01-01 10:00:00'), $result->getCreatedAt());
        self::assertEquals(new DateTimeImmutable('2023-01-01 10:00:00'), $result->getUpdatedAt());
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
            ->willReturn(false);

        $repository = new PersonRepository($pdo);
        $result = $repository->findById(999);

        self::assertNull($result);
    }

    public function testFindByEmailFound(): void
    {
        $personData = $this->getPersonData(1, '12345678909'); // Valid CPF
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM persons WHERE email = :email'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['email' => 'john.doe@example.com'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($personData);

        $repository = new PersonRepository($pdo);
        $result = $repository->findByEmail('john.doe@example.com');

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
    }

    public function testFindByEmailNotFound(): void
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
            ->willReturn(false);

        $repository = new PersonRepository($pdo);
        $result = $repository->findByEmail('nonexistent@example.com');

        self::assertNull($result);
    }

    public function testFindByCpfCnpjFoundWithStringInput(): void
    {
        $personData = $this->getPersonData(1, '12345678909'); // Valid CPF
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM persons WHERE cpfcnpj = :cpfcnpj'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['cpfcnpj' => '12345678909']) // Valid CPF
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($personData);

        $repository = new PersonRepository($pdo);
        $result = $repository->findByCpfCnpj('123.456.789-09'); // String input, will be sanitized

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
        self::assertEquals('12345678909', $result->getCpfCnpj()?->value());
    }

    public function testFindByCpfCnpjFoundWithObjectInput(): void
    {
        $personData = $this->getPersonData(1, '12345678909'); // Valid CPF
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $cpfcnpjObject = CpfCnpj::fromString('12345678909'); // Valid CPF

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM persons WHERE cpfcnpj = :cpfcnpj'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['cpfcnpj' => '12345678909']) // Valid CPF
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($personData);

        $repository = new PersonRepository($pdo);
        $result = $repository->findByCpfCnpj($cpfcnpjObject); // Object input

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
        self::assertEquals('12345678909', $result->getCpfCnpj()?->value());
    }

    public function testFindByCpfCnpjNotFound(): void
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
            ->willReturn(false);

        $repository = new PersonRepository($pdo);
        $result = $repository->findByCpfCnpj('98765432101'); // Invalid but different looking CPF

        self::assertNull($result);
    }

    public function testUpdatePerson(): void
    {
        $person = $this->createPerson(1, '12345678909'); // Valid CPF
        $person->setName('Jane Doe');
        $person->setPhone('(22) 11111-2222');
        $person->setEmail('jane.doe@example.com');
        
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE persons 
                SET name = :name, email = :email, phone = :phone, 
                    cpfcnpj = :cpfcnpj, avatar_url = :avatar_url, updated_at = NOW()
                WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($person) {
                self::assertArrayHasKey('id', $params);
                self::assertSame($person->getId(), $params['id']);
                self::assertArrayHasKey('name', $params);
                self::assertSame('Jane Doe', $params['name']);
                self::assertArrayHasKey('email', $params);
                self::assertSame('jane.doe@example.com', $params['email']);
                self::assertArrayHasKey('phone', $params);
                self::assertSame('22111112222', $params['phone']); // Sanitized
                self::assertArrayHasKey('cpfcnpj', $params);
                self::assertSame('12345678909', $params['cpfcnpj']); // Valid CPF
                return true;
            }))
            ->willReturn(true);

        $repository = new PersonRepository($pdo);
        $result = $repository->update($person);

        self::assertEquals($person, $result);
        self::assertSame('22111112222', $result->getPhone());
        self::assertEquals('12345678909', $result->getCpfCnpj()?->value());
    }

    public function testDeletePerson(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM persons WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $repository = new PersonRepository($pdo);
        $result = $repository->delete(1);

        self::assertTrue($result);
    }

    public function testDeletePersonNotFound(): void
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
            ->method('rowCount')
            ->willReturn(0);

        $repository = new PersonRepository($pdo);
        $result = $repository->delete(999);

        self::assertFalse($result);
    }
}