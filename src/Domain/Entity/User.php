<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use JsonSerializable;

class User implements JsonSerializable
{
    private readonly int $id;

    public function __construct(
        private readonly Person $person,
        private Role $role,
        private string $password,
        private bool $isActive = true,
        private bool $isVerified = false,
        private readonly ?DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $updatedAt = new DateTimeImmutable(),
    ) {
        $this->id = $this->person->getId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->touch();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function markAsVerified(): void
    {
        $this->isVerified = true;
        $this->touch();
    }

    public function markAsUnverified(): void
    {
        $this->isVerified = false;
        $this->touch();
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        $personData = $this->person->toArray();
        unset($personData['id']); // Avoid id duplication

        return \array_merge([
            'id' => $this->id,
            'role_id' => $this->role->getId(),
            'role_name' => $this->role->getName(),
            'is_active' => $this->isActive,
            'is_verified' => $this->isVerified,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ], $personData);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {
        $person = Person::fromArray($data);

        $role = new Role(
            $data['role_id'],
            $data['role_name'],
            null, // Not available in cached data
            new DateTimeImmutable(), // Not available in cached data
            new DateTimeImmutable(),  // Not available in cached data
        );

        return new self(
            person: $person,
            password: $data['password'],
            role: $role,
            isActive: $data['is_active'],
            isVerified: $data['is_verified'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );
    }

    public function getEmail(): string
    {
        return $this->person->getEmail();
    }
}
