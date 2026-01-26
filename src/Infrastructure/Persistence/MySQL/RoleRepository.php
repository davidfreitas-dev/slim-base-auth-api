<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Role;
use App\Domain\Repository\RoleRepositoryInterface;
use DateTimeImmutable;
use PDO;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Role
    {
        $sql = 'SELECT * FROM roles WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? $this->hydrate($data) : null;
    }

    public function findByName(string $name): ?Role
    {
        $sql = 'SELECT * FROM roles WHERE name = :name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['name' => $name]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? $this->hydrate($data) : null;
    }

    private function hydrate(array $data): Role
    {
        return new Role(
            (int)$data['id'],
            $data['name'],
            $data['description'],
            new DateTimeImmutable($data['created_at']),
            new DateTimeImmutable($data['updated_at']),
        );
    }
}
