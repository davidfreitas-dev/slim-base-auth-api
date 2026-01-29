<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use JsonSerializable;

class Person implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?CpfCnpj $cpfcnpj = null,
        public ?string $avatarUrl = null,
        public ?int $id = null,
        public ?DateTimeImmutable $createdAt = new DateTimeImmutable(),
        public ?DateTimeImmutable $updatedAt = new DateTimeImmutable(),
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getCpfCnpj(): ?CpfCnpj
    {
        return $this->cpfcnpj;
    }

    public function setCpfCnpj(?CpfCnpj $cpfcnpj): void
    {
        $this->cpfcnpj = $cpfcnpj;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): void
    {
        $this->avatarUrl = $avatarUrl;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'cpfcnpj' => $this->cpfcnpj?->value(),
            'avatar_url' => $this->avatarUrl,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {

        $cpfcnpj = null;
        if (isset($data['cpfcnpj']) && !empty($data['cpfcnpj'])) {
            // Garante que não é um objeto
            if (is_string($data['cpfcnpj'])) {
                $cpfcnpj = CpfCnpj::fromString($data['cpfcnpj']);
            } elseif ($data['cpfcnpj'] instanceof CpfCnpj) {
                $cpfcnpj = $data['cpfcnpj'];
            }
        }

        return new self(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            cpfcnpj: $cpfcnpj,
            avatarUrl: $data['avatar_url'] ?? null,
            id: $data['id'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
