<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use JsonSerializable;

class ErrorLog implements JsonSerializable
{
    public function __construct(private readonly string $severity, private readonly string $message, private readonly array $context = [], private readonly ?DateTimeImmutable $createdAt = new DateTimeImmutable(), private ?DateTimeImmutable $resolvedAt = null, private ?int $resolvedBy = null, private ?int $id = null)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): void
    {
        $this->resolvedAt = $resolvedAt;
    }

    public function getResolvedBy(): ?int
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?int $resolvedBy): void
    {
        $this->resolvedBy = $resolvedBy;
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt instanceof \DateTimeImmutable;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'message' => $this->message,
            'context' => $this->context,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'resolved_at' => $this->resolvedAt?->format('Y-m-d H:i:s'),
            'resolved_by' => $this->resolvedBy,
        ];
    }
}
