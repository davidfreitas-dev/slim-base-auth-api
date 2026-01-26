<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\ValidateResetCodeRequestDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ValidateResetCodeRequestDTO::class)]
class ValidateResetCodeRequestDTOTest extends TestCase
{
    public function testConstructor(): void
    {
        $email = 'test@example.com';
        $code = '123456';

        $dto = new ValidateResetCodeRequestDTO($email, $code);

        $this->assertSame($email, $dto->email);
        $this->assertSame($code, $dto->code);
    }
}
