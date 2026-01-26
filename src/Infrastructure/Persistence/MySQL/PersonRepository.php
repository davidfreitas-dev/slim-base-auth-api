<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Person;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use PDO;

class PersonRepository implements PersonRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(Person $person): Person
    {
        $sanitizedPhone = \preg_replace('/[^0-9]/', '', $person->getPhone() ?? '') ?: null;
        $sanitizedCpfCnpj = $person->getCpfCnpj()?->value(); // Correctly get the string value

        $sql = 'INSERT INTO persons (name, email, phone, cpfcnpj, avatar_url, created_at, updated_at) 
                VALUES (:name, :email, :phone, :cpfcnpj, :avatar_url, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name' => $person->getName(),
            'email' => $person->getEmail(),
            'phone' => $sanitizedPhone,
            'cpfcnpj' => $sanitizedCpfCnpj,
            'avatar_url' => $person->getAvatarUrl(),
        ]);

        $person->setId((int)$this->pdo->lastInsertId());
        $person->setPhone($sanitizedPhone);
        $person->setCpfCnpj($sanitizedCpfCnpj ? CpfCnpj::fromString($sanitizedCpfCnpj) : null); // Update Person object with CpfCnpj object

        return $person;
    }

    public function findById(int $id): ?Person
    {
        $sql = 'SELECT * FROM persons WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findByEmail(string $email): ?Person
    {
        $sanitizedEmail = \strtolower(\trim($email)); // Assuming email should be stored and searched in lowercase and trimmed
        $sql = 'SELECT * FROM persons WHERE email = :email';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $sanitizedEmail]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findByCpfCnpj(string|CpfCnpj $cpfcnpj): ?Person
    {
        $value = $cpfcnpj instanceof CpfCnpj ? $cpfcnpj->value() : $cpfcnpj;

        $sanitizedCpfCnpj = \preg_replace('/[^0-9]/', '', $value);

        $sql = 'SELECT * FROM persons WHERE cpfcnpj = :cpfcnpj';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cpfcnpj' => $sanitizedCpfCnpj]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function update(Person $person): Person
    {
        $sanitizedPhone = \preg_replace('/[^0-9]/', '', $person->getPhone() ?? '') ?: null;
        $sanitizedCpfCnpj = $person->getCpfCnpj()?->value(); // Correctly get the string value

        $sql = 'UPDATE persons 
                SET name = :name, email = :email, phone = :phone, 
                    cpfcnpj = :cpfcnpj, avatar_url = :avatar_url, updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $person->getId(),
            'name' => $person->getName(),
            'email' => $person->getEmail(),
            'phone' => $sanitizedPhone,
            'cpfcnpj' => $sanitizedCpfCnpj,
            'avatar_url' => $person->getAvatarUrl(),
        ]);

        $person->setPhone($sanitizedPhone);
        $person->setCpfCnpj($sanitizedCpfCnpj ? CpfCnpj::fromString($sanitizedCpfCnpj) : null); // Update Person object with CpfCnpj object

        return $person;
    }

    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM persons WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $data): Person
    {
        return new Person(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'],
            cpfcnpj: $data['cpfcnpj'] ? CpfCnpj::fromString($data['cpfcnpj']) : null, // Convert to CpfCnpj object
            avatarUrl: $data['avatar_url'],
            id: (int)$data['id'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );
    }
}
