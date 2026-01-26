<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\ForgotPasswordRequestDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ForgotPasswordRequestDTO::class)]
class ForgotPasswordRequestDTOTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $email = 'test@example.com';
        $ipAddress = '127.0.0.1';

        $dto = new ForgotPasswordRequestDTO($email, $ipAddress);

        $this->assertSame($email, $dto->getEmail());
        $this->assertSame($ipAddress, $dto->getIpAddress());
    }
}
