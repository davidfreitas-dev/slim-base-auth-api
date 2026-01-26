<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ValidateResetCodeRequestDTO
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
    ) {
    }
}
