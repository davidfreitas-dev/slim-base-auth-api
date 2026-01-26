<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;
use JsonSerializable;

final readonly class CpfCnpj implements JsonSerializable
{
    private string $value;

    private function __construct(string $value)
    {
        if (empty($value)) {
            throw new ValidationException('CPF/CNPJ não pode ser vazio.');
        }

        $clean = preg_replace('/[^0-9]/', '', $value);

        if (!$this->isValid($clean)) {
            throw new ValidationException('O CPF/CNPJ informado não é válido.');
        }

        $this->value = $clean;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        if (strlen($this->value) === 11) {
            // CPF: 000.000.000-00
            return sprintf(
                '%s.%s.%s-%s',
                substr($this->value, 0, 3),
                substr($this->value, 3, 3),
                substr($this->value, 6, 3),
                substr($this->value, 9, 2),
            );
        }

        // CNPJ: 00.000.000/0000-00
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($this->value, 0, 2),
            substr($this->value, 2, 3),
            substr($this->value, 5, 3),
            substr($this->value, 8, 4),
            substr($this->value, 12, 2),
        );
    }

    public function isCpf(): bool
    {
        return strlen($this->value) === 11;
    }

    public function isCnpj(): bool
    {
        return strlen($this->value) === 14;
    }

    public function equals(?CpfCnpj $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private function isValid(string $value): bool
    {
        $length = strlen($value);

        if ($length === 11) {
            return $this->isValidCpf($value);
        }

        if ($length === 14) {
            return $this->isValidCnpj($value);
        }

        return false;
    }

    private function isValidCpf(string $cpf): bool
    {
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $cpf[$i]) * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if (((int) $cpf[9]) !== $digit1) {
            return false;
        }

        // Valida segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $cpf[$i]) * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return ((int) $cpf[10]) === $digit2;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $length = 12;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += ((int) $numbers[$length - $i]) * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        if ($result !== ((int) $digits[0])) {
            return false;
        }

        // Valida segundo dígito verificador
        $length = 13;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += ((int) $numbers[$length - $i]) * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

        return $result === ((int) $digits[1]);
    }
}
