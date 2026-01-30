<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\UserVerification;
use App\Infrastructure\Persistence\MySQL\UserVerificationRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\UserVerificationRepository
 */
final class UserVerificationRepositoryTest extends TestCase
{
    private function createUserVerification(string $token = 'test_token', ?DateTimeImmutable $usedAt = null): UserVerification
    {
        $fixedExpiresAt = new DateTimeImmutable('2023-01-02 10:00:00'); // Fixed date

        return new UserVerification(
            userId: 1,
            token: $token,
            expiresAt: $fixedExpiresAt,
            usedAt: $usedAt,
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2023-01-01 10:00:00'),
        );
    }

    private function getUserVerificationData(string $token = 'test_token', ?string $usedAt = null): array
    {
        $fixedExpiresAt = new DateTimeImmutable('2023-01-02 10:00:00'); // Fixed date

        return [
            'user_id' => 1,
            'token' => $token,
            'expires_at' => $fixedExpiresAt->format('Y-m-d H:i:s'),
            'used_at' => $usedAt,
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];
    }

    public function testCreate(): void
    {
        $verification = $this->createUserVerification();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO user_verifications (user_id, token, expires_at, created_at, updated_at)'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'user_id' => $verification->getUserId(),
                'token' => $verification->getToken(),
                'expires_at' => $verification->getExpiresAt()->format('Y-m-d H:i:s'),
            ])
            ->willReturn(true);

        $repository = new UserVerificationRepository($pdo);
        $result = $repository->create($verification);

        self::assertEquals($verification, $result);
    }

    public function testFindByTokenFound(): void
    {
        $verificationData = $this->getUserVerificationData();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM user_verifications WHERE token = :token'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['token' => 'test_token'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($verificationData);

        $repository = new UserVerificationRepository($pdo);
        $result = $repository->findByToken('test_token');

        self::assertNotNull($result);
        self::assertEquals($this->createUserVerification(), $result);
    }

    public function testFindByTokenNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $repository = new UserVerificationRepository($pdo);
        $result = $repository->findByToken('non_existent_token');

        self::assertNull($result);
    }

    public function testMarkAsUsed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE user_verifications SET used_at = NOW(), updated_at = NOW() WHERE token = :token'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['token' => 'token_to_mark_used'])
            ->willReturn(true);

        $repository = new UserVerificationRepository($pdo);
        $repository->markAsUsed('token_to_mark_used');
        
        self::assertTrue(true);
    }

    public function testDeleteOldVerifications(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM user_verifications WHERE user_id = :user_id AND (expires_at < NOW() OR used_at IS NOT NULL)'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 1])
            ->willReturn(true);

        $repository = new UserVerificationRepository($pdo);
        $repository->deleteOldVerifications(1);
        
        self::assertTrue(true);
    }
}