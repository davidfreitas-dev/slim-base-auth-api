<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\Service\ErrorLoggerService;
use App\Domain\Entity\ErrorLog;
use App\Domain\Repository\ErrorLogRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ErrorLoggerServiceTest extends TestCase
{
    private ErrorLogRepositoryInterface $mockErrorLogRepository;
    private ErrorLoggerService $errorLoggerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockErrorLogRepository = $this->createMock(ErrorLogRepositoryInterface::class);
        $this->errorLoggerService = new ErrorLoggerService($this->mockErrorLogRepository);
    }

    public function testLogErrorWithoutResolvedBy(): void
    {
        $severity = 'ERROR';
        $message = 'Test error message';
        $context = ['file' => 'test.php'];

        $expectedErrorLog = new ErrorLog(
            severity: $severity,
            message: $message,
            context: $context,
            resolvedAt: null,
            resolvedBy: null,
            id: 1, // Assume ID is set after saving
        );

        $this->mockErrorLogRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ErrorLog $errorLog) use ($severity, $message, $context) {
                $this->assertSame($severity, $errorLog->getSeverity());
                $this->assertSame($message, $errorLog->getMessage());
                $this->assertSame($context, $errorLog->getContext());
                $this->assertNull($errorLog->getResolvedAt());
                $this->assertNull($errorLog->getResolvedBy());
                return true;
            }))
            ->willReturn($expectedErrorLog);

        $result = $this->errorLoggerService->log($severity, $message, $context);

        $this->assertSame($expectedErrorLog, $result);
    }

    public function testLogErrorWithResolvedBy(): void
    {
        $severity = 'CRITICAL';
        $message = 'Critical test error';
        $context = ['user_id' => 123];
        $resolvedBy = 123;

        $expectedErrorLog = new ErrorLog(
            severity: $severity,
            message: $message,
            context: $context,
            resolvedAt: new DateTimeImmutable(),
            resolvedBy: $resolvedBy,
            id: 2,
        );

        $this->mockErrorLogRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ErrorLog $errorLog) use ($severity, $message, $context, $resolvedBy) {
                $this->assertSame($severity, $errorLog->getSeverity());
                $this->assertSame($message, $errorLog->getMessage());
                $this->assertSame($context, $errorLog->getContext());
                $this->assertNotNull($errorLog->getResolvedAt());
                $this->assertSame($resolvedBy, $errorLog->getResolvedBy());
                return true;
            }))
            ->willReturn($expectedErrorLog);

        $result = $this->errorLoggerService->log($severity, $message, $context, $resolvedBy);

        $this->assertSame($expectedErrorLog, $result);
    }

    public function testMarkAsResolvedSuccess(): void
    {
        $errorLogId = 1;
        $resolvedByUserId = 123;

        $this->mockErrorLogRepository
            ->expects(self::once())
            ->method('markAsResolved')
            ->with($errorLogId, $resolvedByUserId)
            ->willReturn(true);

        $result = $this->errorLoggerService->markAsResolved($errorLogId, $resolvedByUserId);

        $this->assertTrue($result);
    }

    public function testMarkAsResolvedFailure(): void
    {
        $errorLogId = 1;
        $resolvedByUserId = 123;

        $this->mockErrorLogRepository
            ->expects(self::once())
            ->method('markAsResolved')
            ->with($errorLogId, $resolvedByUserId)
            ->willReturn(false);

        $result = $this->errorLoggerService->markAsResolved($errorLogId, $resolvedByUserId);

        $this->assertFalse($result);
    }
}
