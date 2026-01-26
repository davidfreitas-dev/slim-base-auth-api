<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserProfileRequestDTO
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
        #[Assert\Callback([self::class, 'validateCpfCnpj'])]
        public readonly ?string $cpfcnpj,
        public readonly ?UploadedFileInterface $profileImage = null,
    ) {
    }

    public static function fromArray(array $data, int $userId, ?UploadedFileInterface $profileImage = null): self
    {
        return new self(
            userId: $userId,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            cpfcnpj: $data['cpfcnpj'] ?? null,
            profileImage: $profileImage,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'cpfcnpj' => $this->cpfcnpj,
            'profile_image' => $this->profileImage,
        ];
    }

    public static function validateCpfCnpj(?string $value, $context): void
    {
        if ($value === null) {
            return;
        }

        $cpfcnpj = preg_replace('/[^0-9]/', '', $value);

        if (strlen($cpfcnpj) !== 11 && strlen($cpfcnpj) !== 14) {
            $context->buildViolation('CPF/CNPJ inválido.')
                ->addViolation();
            return;
        }

        // Validação básica - você pode melhorar com validação de dígitos verificadores
        if (preg_match('/^(\d)\1+$/', $cpfcnpj)) {
            $context->buildViolation('CPF/CNPJ inválido.')
                ->addViolation();
        }
    }

    public function validateProfileImage(): ?string
    {
        if ($this->profileImage === null) {
            return null;
        }

        $error = $this->profileImage->getError();
        if ($error !== UPLOAD_ERR_OK) {
            return 'Erro ao fazer upload da imagem.';
        }

        $size = $this->profileImage->getSize();
        if ($size > 2 * 1024 * 1024) { // 2MB
            return 'O arquivo é muito grande. O tamanho máximo permitido é 2MB.';
        }

        $mimeType = $this->profileImage->getClientMediaType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            return 'Tipo de arquivo inválido. Os tipos permitidos são: JPEG, PNG, GIF.';
        }

        return null;
    }
}
