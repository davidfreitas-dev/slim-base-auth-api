<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validate')]
class ChangePasswordRequestDTO
{
    public function __construct(
        public readonly int $userId,
        #[Assert\NotBlank(message: 'A senha atual é obrigatória.')]
        public readonly string $currentPassword,
        #[Assert\NotBlank(message: 'A nova senha é obrigatória.')]
        #[Assert\Length(min: 6, minMessage: 'A nova senha deve ter no mínimo {{ limit }} caracteres.')]
        public readonly string $newPassword,
        #[Assert\NotBlank(message: 'A confirmação da nova senha é obrigatória.')]
        public readonly string $newPasswordConfirm,
    ) {
    }

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            $userId,
            $data['current_password'] ?? '',
            $data['new_password'] ?? '',
            $data['new_password_confirm'] ?? '',
        );
    }

    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->newPassword !== $this->newPasswordConfirm) {
            $context->buildViolation('As senhas não conferem.')
                ->atPath('newPasswordConfirm')
                ->addViolation();
        }
    }
}
