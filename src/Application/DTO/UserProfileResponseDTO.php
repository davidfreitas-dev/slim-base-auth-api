<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\User;
use JsonSerializable;

/**
 * DTO for safely representing a user's profile data in API responses.
 */
readonly class UserProfileResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $cpfcnpj,
        public ?string $avatarUrl,
        public bool $isActive,
        public bool $isVerified,
        public int $roleId,
        public string $roleName,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * Creates a DTO from a User entity.
     */
    public static function fromEntity(User $user): self
    {
        $person = $user->getPerson();

        return new self(
            id: $user->getId(),
            name: $person->getName(),
            email: $person->getEmail(),
            phone: $person->getPhone(),
            cpfcnpj: $user->getPerson()->getCpfCnpj()?->value(),
            avatarUrl: $person->getAvatarUrl(),
            isActive: $user->isActive(),
            isVerified: $user->isVerified(),
            roleId: $user->getRole()->getId(),
            roleName: $user->getRole()->getName(),
            createdAt: $user->getCreatedAt()->format('Y-m-d H:i:s'),
            updatedAt: $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'cpfcnpj' => $this->cpfcnpj,
            'avatar_url' => $this->avatarUrl,
            'is_active' => $this->isActive,
            'is_verified' => $this->isVerified,
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
