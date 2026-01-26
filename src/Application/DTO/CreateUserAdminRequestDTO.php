<?php

declare(strict_types=1);

namespace App\Application\DTO;

class CreateUserAdminRequestDTO extends RegisterUserRequestDTO
{
    public function __construct(
        string $name,
        string $email,
        string $password,
        ?string $phone,
        ?string $cpfcnpj,
        public readonly ?string $roleName = 'user',
    ) {
        parent::__construct($name, $email, $password, $phone, $cpfcnpj);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            phone: $data['phone'] ?? null,
            cpfcnpj: $data['cpfcnpj'] ?? null,
            roleName: $data['role'] ?? 'user',
        );
    }

    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'role' => $this->roleName,
        ]);
    }
}
