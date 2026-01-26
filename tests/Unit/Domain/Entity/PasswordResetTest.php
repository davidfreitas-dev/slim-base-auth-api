<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\PasswordReset;
use App\Domain\ValueObject\Code;
use DateTimeImmutable;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $id = 1;
        $userId = 123;
        $code = Code::from('123456');
        $expiresAt = new DateTimeImmutable('+1 hour');
        $usedAt = null;
        $ipAddress = '127.0.0.1';

        $passwordReset = new PasswordReset($id, $userId, $code, $expiresAt, $usedAt, $ipAddress);

        $this->assertInstanceOf(PasswordReset::class, $passwordReset);
        $this->assertSame($id, $passwordReset->getId());
        $this->assertSame($userId, $passwordReset->getUserId());
        $this->assertSame($code, $passwordReset->getCode());
        $this->assertSame($expiresAt, $passwordReset->getExpiresAt());
        $this->assertNull($passwordReset->getUsedAt());
        $this->assertSame($ipAddress, $passwordReset->getIpAddress());
    }

    public function testMarkAsUsedSetsUsedAt(): void
    {
        $passwordReset = new PasswordReset(
            1,
            123,
            Code::from('123456'),
            new DateTimeImmutable('+1 hour'),
            null,
            '127.0.0.1'
        );

        $this->assertNull($passwordReset->getUsedAt());

        $passwordReset->markAsUsed();

        $this->assertInstanceOf(DateTimeImmutable::class, $passwordReset->getUsedAt());
    }
}
