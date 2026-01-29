<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\PasswordReset;
use App\Domain\ValueObject\Code;
use App\Infrastructure\Persistence\MySQL\PasswordResetRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\PasswordResetRepository
 */
final class PasswordResetRepositoryTest extends TestCase
{
    private function createPasswordReset(int $id = 0): PasswordReset
    {
        return new PasswordReset(
            id: $id,
            userId: 1,
            code: Code::from('123456'),
            expiresAt: new DateTimeImmutable('+1 hour'),
            usedAt: null,
            ipAddress: '127.0.0.1',
        );
    }

    public function testSave(): void
    {
        $passwordReset = $this->createPasswordReset();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                $normalizedSql = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
                return str_contains($normalizedSql, 'insert into password_resets');
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($passwordReset) {
                self::assertArrayHasKey('user_id', $params);
                self::assertSame($passwordReset->getUserId(), $params['user_id']);
                self::assertArrayHasKey('code', $params);
                self::assertSame($passwordReset->getCode()->value, $params['code']);
                self::assertArrayHasKey('expires_at', $params);
                self::assertSame($passwordReset->getExpiresAt()->format('Y-m-d H:i:s'), $params['expires_at']);
                self::assertArrayHasKey('used_at', $params);
                self::assertNull($params['used_at']);
                self::assertArrayHasKey('ip_address', $params);
                self::assertSame($passwordReset->getIpAddress(), $params['ip_address']);
                return true;
            }))
            ->willReturn(true);

        $repository = new PasswordResetRepository($pdo);
        $repository->save($passwordReset);
        
        // As assertions são feitas no callback acima
        self::assertTrue(true);
    }

    public function testFindByCodeFound(): void
    {
        $passwordReset = $this->createPasswordReset(1);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        
        $passwordResetData = [
            'id' => 1,
            'user_id' => 1,
            'code' => '123456',
            'expires_at' => $passwordReset->getExpiresAt()->format('Y-m-d H:i:s'),
            'used_at' => null,
            'ip_address' => '127.0.0.1',
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Normaliza SQL: lowercase e remove espaços extras
                $normalizedSql = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
                $expectedSql = 'select * from password_resets where code = :code and used_at is null and expires_at > now()';
                return str_contains($normalizedSql, $expectedSql);
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['code' => '123456'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($passwordResetData);

        $repository = new PasswordResetRepository($pdo);
        $result = $repository->findByCode(Code::from('123456'));

        self::assertNotNull($result);
        self::assertEquals($passwordReset->getId(), $result->getId());
        self::assertEquals($passwordReset->getUserId(), $result->getUserId());
        self::assertEquals($passwordReset->getCode()->value, $result->getCode()->value);
        self::assertEquals($passwordReset->getExpiresAt()->getTimestamp(), $result->getExpiresAt()->getTimestamp());
        self::assertNull($result->getUsedAt());
        self::assertEquals($passwordReset->getIpAddress(), $result->getIpAddress());
    }

    public function testFindByCodeNotFound(): void
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
            ->willReturn(false);

        $repository = new PasswordResetRepository($pdo);
        $result = $repository->findByCode(Code::from('654321'));

        self::assertNull($result);
    }

    public function testMarkAsUsed(): void
    {
        $code = Code::from('123456');
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                $normalizedSql = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
                return str_contains($normalizedSql, 'update password_resets set used_at = now()');
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['code' => '123456'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $repository = new PasswordResetRepository($pdo);
        $result = $repository->markAsUsed($code);

        self::assertTrue($result);
    }

    public function testMarkAsUsedNotFound(): void
    {
        $code = Code::from('123456');
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $repository = new PasswordResetRepository($pdo);
        $result = $repository->markAsUsed($code);

        self::assertFalse($result);
    }

    public function testDeleteExpired(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) {
                $normalizedSql = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
                return str_contains($normalizedSql, 'delete from password_resets');
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);

        $repository = new PasswordResetRepository($pdo);
        $result = $repository->deleteExpired();

        self::assertEquals(5, $result);
    }
}