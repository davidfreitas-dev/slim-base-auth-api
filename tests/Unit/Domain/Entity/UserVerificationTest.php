<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\UserVerification;
use DateTimeImmutable;
use Tests\TestCase;

class UserVerificationTest extends TestCase
{
    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $userId = 1;
        $token = 'some_verification_token';
        $expiresAt = new DateTimeImmutable('+1 day');

        $verification = new UserVerification($userId, $token, $expiresAt);

        $this->assertInstanceOf(UserVerification::class, $verification);
        $this->assertSame($userId, $verification->getUserId());
        $this->assertSame($token, $verification->getToken());
        $this->assertSame($expiresAt, $verification->getExpiresAt());
        $this->assertNull($verification->getUsedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $verification->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $verification->getUpdatedAt());
    }

    public function testIsUsedReturnsCorrectBoolean(): void
    {
        $verification = new UserVerification(1, 'token', new DateTimeImmutable('+1 day'));
        $this->assertFalse($verification->isUsed());

        $verification->markAsUsed();
        $this->assertTrue($verification->isUsed());
    }

    public function testIsExpiredReturnsCorrectBoolean(): void
    {
        $verificationFuture = new UserVerification(1, 'token', new DateTimeImmutable('+1 day'));
        $this->assertFalse($verificationFuture->isExpired());

        $verificationPast = new UserVerification(1, 'token', new DateTimeImmutable('-1 day'));
        $this->assertTrue($verificationPast->isExpired());
    }

    public function testMarkAsUsedSetsUsedAtAndUpdatesUpdatedAt(): void
    {
        $verification = new UserVerification(1, 'token', new DateTimeImmutable('+1 day'));
        $initialUpdatedAt = $verification->getUpdatedAt();
        sleep(1);
        $verification->markAsUsed();

        $this->assertNotNull($verification->getUsedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $verification->getUsedAt());
        $this->assertNotEquals($initialUpdatedAt, $verification->getUpdatedAt());
    }
}
