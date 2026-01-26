<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\ErrorLog;
use DateTimeImmutable;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $errorLog = new ErrorLog(
            'error',
            'Test error message',
            ['file' => 'test.php', 'line' => 10]
        );

        $this->assertInstanceOf(ErrorLog::class, $errorLog);
        $this->assertSame('error', $errorLog->getSeverity());
        $this->assertSame('Test error message', $errorLog->getMessage());
        $this->assertSame(['file' => 'test.php', 'line' => 10], $errorLog->getContext());
        $this->assertInstanceOf(DateTimeImmutable::class, $errorLog->getCreatedAt());
        $this->assertNull($errorLog->getResolvedAt());
        $this->assertNull($errorLog->getResolvedBy());
        $this->assertNull($errorLog->getId());
        $this->assertFalse($errorLog->isResolved());
    }

    public function testCanSetAndGetId(): void
    {
        $errorLog = new ErrorLog('error', 'Test error');
        $errorLog->setId(1);
        $this->assertSame(1, $errorLog->getId());
    }

    public function testCanSetAndGetResolvedAt(): void
    {
        $errorLog = new ErrorLog('error', 'Test error');
        $resolvedAt = new DateTimeImmutable();
        $errorLog->setResolvedAt($resolvedAt);
        $this->assertSame($resolvedAt, $errorLog->getResolvedAt());
    }

    public function testCanSetAndGetResolvedBy(): void
    {
        $errorLog = new ErrorLog('error', 'Test error');
        $errorLog->setResolvedBy(123);
        $this->assertSame(123, $errorLog->getResolvedBy());
    }

    public function testIsResolvedReturnsTrueWhenResolvedAtIsSet(): void
    {
        $errorLog = new ErrorLog('error', 'Test error');
        $errorLog->setResolvedAt(new DateTimeImmutable());
        $this->assertTrue($errorLog->isResolved());
    }

    public function testIsResolvedReturnsFalseWhenResolvedAtIsNull(): void
    {
        $errorLog = new ErrorLog('error', 'Test error');
        $this->assertFalse($errorLog->isResolved());
    }
}
