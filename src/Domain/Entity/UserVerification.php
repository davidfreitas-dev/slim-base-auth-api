<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

class UserVerification
{
    public function __construct(private readonly int $userId, private readonly string $token, private readonly DateTimeImmutable $expiresAt, private ?DateTimeImmutable $usedAt = null, private readonly ?DateTimeImmutable $createdAt = new DateTimeImmutable(), private ?DateTimeImmutable $updatedAt = new DateTimeImmutable())
    {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt instanceof \DateTimeImmutable;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function markAsUsed(): void
    {
        $this->usedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
