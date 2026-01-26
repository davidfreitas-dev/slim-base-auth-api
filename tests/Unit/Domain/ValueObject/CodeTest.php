<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Code;
use InvalidArgumentException;
use Tests\TestCase;

class CodeTest extends TestCase
{
    public function testThatCodeCanBeCreatedFromValidString(): void
    {
        $code = Code::from('123456');
        $this->assertSame('123456', $code->value);
    }

    public function testThatCodeThrowsExceptionForInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Code::from('123');
    }

    public function testThatCodeThrowsExceptionForNonNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Code::from('abcdef');
    }

    public function testThatCodeCanBeGenerated(): void
    {
        $code = Code::generate();
        $this->assertIsString($code->value);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code->value);
    }

    public function testThatGeneratedCodeIsA6DigitNumber(): void
    {
        $code = Code::generate();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code->value);
    }

    public function testToStringMethodReturnsTheCodeValue(): void
    {
        $code = Code::from('123456');
        $this->assertSame('123456', (string) $code);
    }
}
