<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\ValidationException;
use App\Domain\ValueObject\CpfCnpj;
use PHPUnit\Framework\TestCase;

final class CpfCnpjTest extends TestCase
{
    // CPF Test Data
    private const VALID_CPF_UNFORMATTED = '11144477735';
    private const VALID_CPF_FORMATTED = '111.444.777-35';
    private const INVALID_CPF = '11144477736';
    private const ALL_SAME_DIGITS_CPF = '11111111111';

    // CNPJ Test Data
    private const VALID_CNPJ_UNFORMATTED = '11444777000161';
    private const VALID_CNPJ_FORMATTED = '11.444.777/0001-61';
    private const INVALID_CNPJ = '11444777000162';
    private const ALL_SAME_DIGITS_CNPJ = '11111111111111';

    public function testFromStringWithValidCpf(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertSame(self::VALID_CPF_UNFORMATTED, $cpf->value());

        $cpfFormatted = CpfCnpj::fromString(self::VALID_CPF_FORMATTED);
        $this->assertSame(self::VALID_CPF_UNFORMATTED, $cpfFormatted->value());
    }

    public function testFromStringWithValidCnpj(): void
    {
        $cnpj = CpfCnpj::fromString(self::VALID_CNPJ_UNFORMATTED);
        $this->assertSame(self::VALID_CNPJ_UNFORMATTED, $cnpj->value());

        $cnpjFormatted = CpfCnpj::fromString(self::VALID_CNPJ_FORMATTED);
        $this->assertSame(self::VALID_CNPJ_UNFORMATTED, $cnpjFormatted->value());
    }

    public function testFromStringWithEmptyValueThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('CPF/CNPJ não pode ser vazio.');
        CpfCnpj::fromString('');
    }

    public function testFromStringWithInvalidLengthThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O CPF/CNPJ informado não é válido.');
        CpfCnpj::fromString('12345');
    }

    public function testFromStringWithInvalidCpfThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O CPF/CNPJ informado não é válido.');
        CpfCnpj::fromString(self::INVALID_CPF);
    }

    public function testFromStringWithAllSameDigitsCpfThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O CPF/CNPJ informado não é válido.');
        CpfCnpj::fromString(self::ALL_SAME_DIGITS_CPF);
    }

    public function testFromStringWithInvalidCnpjThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O CPF/CNPJ informado não é válido.');
        CpfCnpj::fromString(self::INVALID_CNPJ);
    }

    public function testFromStringWithAllSameDigitsCnpjThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O CPF/CNPJ informado não é válido.');
        CpfCnpj::fromString(self::ALL_SAME_DIGITS_CNPJ);
    }

    public function testValue(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertSame(self::VALID_CPF_UNFORMATTED, $cpf->value());
    }

    public function testFormattedCpf(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertSame(self::VALID_CPF_FORMATTED, $cpf->formatted());
    }

    public function testFormattedCnpj(): void
    {
        $cnpj = CpfCnpj::fromString(self::VALID_CNPJ_UNFORMATTED);
        $this->assertSame(self::VALID_CNPJ_FORMATTED, $cnpj->formatted());
    }

    public function testIsCpf(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertTrue($cpf->isCpf());
        $this->assertFalse($cpf->isCnpj());
    }

    public function testIsCnpj(): void
    {
        $cnpj = CpfCnpj::fromString(self::VALID_CNPJ_UNFORMATTED);
        $this->assertTrue($cnpj->isCnpj());
        $this->assertFalse($cnpj->isCpf());
    }

    public function testEquals(): void
    {
        $cpf1 = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $cpf2 = CpfCnpj::fromString(self::VALID_CPF_FORMATTED);
        $cnpj = CpfCnpj::fromString(self::VALID_CNPJ_UNFORMATTED);

        $this->assertTrue($cpf1->equals($cpf2));
        $this->assertFalse($cpf1->equals($cnpj));
        $this->assertFalse($cpf1->equals(null));
    }

    public function testToString(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertSame(self::VALID_CPF_UNFORMATTED, (string) $cpf);
    }

    public function testJsonSerialize(): void
    {
        $cpf = CpfCnpj::fromString(self::VALID_CPF_UNFORMATTED);
        $this->assertSame(self::VALID_CPF_UNFORMATTED, $cpf->jsonSerialize());
    }
}
