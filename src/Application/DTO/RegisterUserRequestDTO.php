<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterUserRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'O nome é obrigatório.')]
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'O nome deve ter pelo menos {{ limit }} caracteres.',
            maxMessage: 'O nome não pode ter mais de {{ limit }} caracteres.',
        )]
        public readonly string $name,
        #[Assert\NotBlank(message: 'O e-mail é obrigatório.')]
        #[Assert\Email(message: 'O e-mail "{{ value }}" não é um e-mail válido.')]
        public readonly string $email,
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        #[Assert\Length(min: 6, minMessage: 'A senha deve ter no mínimo {{ limit }} caracteres.')]
        public readonly string $password,
        #[Assert\Regex(
            pattern: '/^\(?[1-9]{2}\)?\s?9?\d{4}-?\d{4}$/',
            message: 'O formato do telefone é inválido.',
        )]
        public readonly ?string $phone,
        public readonly ?string $cpfcnpj,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            phone: $data['phone'] ?? null,
            cpfcnpj: $data['cpfcnpj'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone,
            'cpfcnpj' => $this->cpfcnpj,
        ];
    }
}
