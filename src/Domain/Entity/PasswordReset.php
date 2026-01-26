<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Code;
use DateTimeImmutable;

class PasswordReset
{
    public function __construct(private readonly ?int $id, private readonly int $userId, private readonly Code $code, private readonly DateTimeImmutable $expiresAt, private ?DateTimeImmutable $usedAt, private readonly string $ipAddress)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCode(): Code
    {
        return $this->code;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function markAsUsed(): void
    {
        $this->usedAt = new DateTimeImmutable(); // Set usedAt to current time
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
}
