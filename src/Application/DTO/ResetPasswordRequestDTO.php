<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validate')]
readonly class ResetPasswordRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'O e-mail é obrigatório.')]
        #[Assert\Email(message: 'O e-mail "{{ value }}" não é um e-mail válido.')]
        public string $email,
        #[Assert\NotBlank(message: 'O código é obrigatório.')]
        #[Assert\Length(
            min: 6,
            max: 6,
            exactMessage: 'O código deve ter exatamente {{ limit }} dígitos.',
        )]
        #[Assert\Regex(
            pattern: '/^\d+$/',
            message: 'O código deve conter apenas dígitos.',
        )]
        public string $code,
        #[Assert\NotBlank(message: 'A senha é obrigatória.')]
        #[Assert\Length(min: 6, minMessage: 'A senha deve ter no mínimo {{ limit }} caracteres.')]
        public string $password,
        #[Assert\NotBlank(message: 'A confirmação da senha é obrigatória.')]
        public string $passwordConfirm,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            code: $data['code'] ?? '',
            password: $data['password'] ?? '',
            passwordConfirm: $data['password_confirm'] ?? '',
        );
    }

    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->password !== $this->passwordConfirm) {
            $context->buildViolation('As senhas não conferem.')
                ->atPath('passwordConfirm')
                ->addViolation();
        }
    }
}
