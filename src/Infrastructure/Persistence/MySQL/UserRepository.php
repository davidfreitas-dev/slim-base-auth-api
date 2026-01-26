<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use PDO;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {
    }

    public function create(User $user): User
    {
        $sql = 'INSERT INTO users (id, password, is_active, is_verified, role_id, created_at, updated_at) 
                VALUES (:id, :password, :is_active, :is_verified, :role_id, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $user->getId(),
            'password' => $user->getPassword(),
            'is_active' => (int)$user->isActive(),
            'is_verified' => (int)$user->isVerified(),
            'role_id' => $user->getRole()->getId(),
        ]);

        return $user;
    }

    public function findById(int $id): ?User
    {
        $sql = 'SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at, 
                       r.id as role_id, r.name as role_name, r.description as role_description, r.created_at as role_created_at, r.updated_at as role_updated_at, p.* 
                FROM users u
                INNER JOIN persons p ON u.id = p.id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $sql = 'SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at, 
                       r.id as role_id, r.name as role_name, r.description as role_description, r.created_at as role_created_at, r.updated_at as role_updated_at, p.* 
                FROM users u
                INNER JOIN persons p ON u.id = p.id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE p.email = :email';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findByCpfCnpj(string $cpfcnpj): ?User
    {
        $sql = 'SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at, 
                       r.id as role_id, r.name as role_name, r.description as role_description, r.created_at as role_created_at, r.updated_at as role_updated_at, p.* 
                FROM users u
                INNER JOIN persons p ON u.id = p.id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE p.cpfcnpj = :cpfcnpj';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cpfcnpj' => $cpfcnpj]);

        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT u.password, u.is_active, u.is_verified, u.created_at as user_created_at, u.updated_at as user_updated_at, 
                       r.id as role_id, r.name as role_name, r.description as role_description, r.created_at as role_created_at, r.updated_at as role_updated_at, p.* 
                FROM users u
                INNER JOIN persons p ON u.id = p.id
                INNER JOIN roles r ON u.role_id = r.id
                ORDER BY p.name ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch()) {
            $users[] = $this->hydrate($data);
        }

        return $users;
    }

    public function update(User $user): User
    {
        // Update person data
        $this->personRepository->update($user->getPerson());

        // Update user data
        $sql = 'UPDATE users 
                SET password = :password, is_active = :is_active, is_verified = :is_verified, role_id = :role_id, updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $user->getId(),
            'password' => $user->getPassword(),
            'is_active' => (int)$user->isActive(),
            'is_verified' => (int)$user->isVerified(),
            'role_id' => $user->getRole()->getId(),
        ]);

        return $user;
    }

    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM users WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) as total FROM users';
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();

        return (int)$result['total'];
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $sql = 'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $userId,
            'password' => $newPassword,
        ]);
    }

    public function markUserAsVerified(int $userId): void
    {
        $sql = 'UPDATE users SET is_verified = TRUE, updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }

    private function hydrate(array $data): User
    {
        $person = new Person(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'],
            cpfcnpj: $data['cpfcnpj'] !== null ? CpfCnpj::fromString($data['cpfcnpj']) : null,
            avatarUrl: $data['avatar_url'] ?? null,
            id: (int)$data['id'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );

        $role = new Role(
            id: (int)$data['role_id'],
            name: $data['role_name'],
            description: $data['role_description'],
            createdAt: new DateTimeImmutable($data['role_created_at']),
            updatedAt: new DateTimeImmutable($data['role_updated_at']),
        );

        return new User(
            person: $person,
            password: $data['password'],
            role: $role,
            isActive: (bool)$data['is_active'],
            isVerified: (bool)$data['is_verified'],
            createdAt: new DateTimeImmutable($data['user_created_at']),
            updatedAt: new DateTimeImmutable($data['user_updated_at']),
        );
    }
}
