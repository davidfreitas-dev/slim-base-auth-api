<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use JsonSerializable;

class Role implements JsonSerializable
{
    public function __construct(private readonly int $id, private readonly string $name, private readonly ?string $description, private readonly DateTimeImmutable $createdAt, private readonly DateTimeImmutable $updatedAt)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
