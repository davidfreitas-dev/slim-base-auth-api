<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\ErrorLog;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ErrorLogTest extends TestCase
{
    private function createErrorLog(
        string $severity = 'error',
        string $message = 'Test error message',
        array $context = ['file' => 'test.php', 'line' => 10],
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $resolvedAt = null,
        ?int $resolvedBy = null,
        ?int $id = null
    ): ErrorLog {
        $log = new ErrorLog(
            $severity,
            $message,
            $context,
            $createdAt ?? new DateTimeImmutable(),
        );

        if ($id !== null) {
            $log->setId($id);
        }
        if ($resolvedAt !== null) {
            $log->setResolvedAt($resolvedAt);
        }
        if ($resolvedBy !== null) {
            $log->setResolvedBy($resolvedBy);
        }

        return $log;
    }

    public function testCanBeInstantiatedAndGettersWork(): void
    {
        $createdAt = new DateTimeImmutable();
        $errorLog = $this->createErrorLog(
            'error',
            'Test error',
            ['file' => 'test.php'],
            $createdAt,
            null,
            null,
            1
        );

        $this->assertInstanceOf(ErrorLog::class, $errorLog);
        $this->assertSame(1, $errorLog->getId());
        $this->assertSame('error', $errorLog->getSeverity());
        $this->assertSame('Test error', $errorLog->getMessage());
        $this->assertSame(['file' => 'test.php'], $errorLog->getContext());
        $this->assertSame($createdAt, $errorLog->getCreatedAt());
        $this->assertNull($errorLog->getResolvedAt());
        $this->assertNull($errorLog->getResolvedBy());
        $this->assertFalse($errorLog->isResolved());
    }

    public function testCanSetAndGetId(): void
    {
        $errorLog = $this->createErrorLog();
        $errorLog->setId(123);
        $this->assertSame(123, $errorLog->getId());
    }

    public function testCanSetAndGetResolvedAt(): void
    {
        $errorLog = $this->createErrorLog();
        $resolvedAt = new DateTimeImmutable();
        $errorLog->setResolvedAt($resolvedAt);
        $this->assertSame($resolvedAt, $errorLog->getResolvedAt());
    }

    public function testCanSetAndGetResolvedBy(): void
    {
        $errorLog = $this->createErrorLog();
        $errorLog->setResolvedBy(1);
        $this->assertSame(1, $errorLog->getResolvedBy());
    }

    public function testIsResolvedReturnsCorrectState(): void
    {
        $errorLog = $this->createErrorLog();
        $this->assertFalse($errorLog->isResolved());

        $errorLog->setResolvedAt(new DateTimeImmutable());
        $this->assertTrue($errorLog->isResolved());

        $errorLog->setResolvedAt(null);
        $this->assertFalse($errorLog->isResolved());
    }

    public function testJsonSerializeForUnresolvedError(): void
    {
        $createdAt = new DateTimeImmutable();
        $errorLog = $this->createErrorLog(
            'warning',
            'A test warning',
            ['user_id' => 42],
            $createdAt,
            null,
            null,
            101
        );

        $expected = [
            'id' => 101,
            'severity' => 'warning',
            'message' => 'A test warning',
            'context' => ['user_id' => 42],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'resolved_at' => null,
            'resolved_by' => null,
        ];

        $this->assertSame($expected, $errorLog->jsonSerialize());
    }

    public function testJsonSerializeForResolvedError(): void
    {
        $createdAt = new DateTimeImmutable();
        $resolvedAt = new DateTimeImmutable('+1 hour');
        $errorLog = $this->createErrorLog(
            'info',
            'An informational message',
            [],
            $createdAt,
            $resolvedAt,
            5,
            202
        );

        $expected = [
            'id' => 202,
            'severity' => 'info',
            'message' => 'An informational message',
            'context' => [],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'resolved_at' => $resolvedAt->format('Y-m-d H:i:s'),
            'resolved_by' => 5,
        ];

        $this->assertSame($expected, $errorLog->jsonSerialize());
    }
}