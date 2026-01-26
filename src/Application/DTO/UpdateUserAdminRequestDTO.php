<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserAdminRequestDTO
{
    public function __construct(
        public readonly int $userId,
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'O nome deve ter pelo menos {{ limit }} caracteres.',
            maxMessage: 'O nome não pode ter mais de {{ limit }} caracteres.',
        )]
        public readonly ?string $name,
        #[Assert\Email(message: 'O e-mail "{{ value }}" não é um e-mail válido.')]
        public readonly ?string $email,
        #[Assert\Regex(
            pattern: '/^\(?[1-9]{2}\)?\s?9?\d{4}-?\d{4}$/',
            message: 'O formato do telefone é inválido.',
        )]
        public readonly ?string $phone,
        #[Assert\Type('string')]
        public readonly ?string $cpfcnpj,
        public readonly ?string $roleName,
        #[Assert\Type('bool')]
        public readonly ?bool $isActive,
        #[Assert\Type('bool')]
        public readonly ?bool $isVerified,
    ) {
    }

    public static function fromArray(int $userId, array $data): self
    {
        return new self(
            userId: $userId,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            cpfcnpj: $data['cpfcnpj'] ?? null,
            roleName: $data['role'] ?? null,
            isActive: $data['is_active'] ?? null,
            isVerified: $data['is_verified'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'cpfcnpj' => $this->cpfcnpj,
            'role' => $this->roleName,
            'is_active' => $this->isActive,
            'is_verified' => $this->isVerified,
        ];
    }
}
