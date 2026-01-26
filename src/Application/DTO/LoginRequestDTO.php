<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class LoginRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'O e-mail é obrigatório.')]
        #[Assert\Email(message: 'O e-mail "{{ value }}" não é um e-mail válido.')]
        public string $email,
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        public string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
        );
    }
}
