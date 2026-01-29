<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Logging\Monolog;

use App\Application\Service\ErrorLoggerService;
use App\Infrastructure\Logging\Monolog\DatabaseErrorLogHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \App\Infrastructure\Logging\Monolog\DatabaseErrorLogHandler
 */
final class DatabaseErrorLogHandlerTest extends TestCase
{
    private function createHandler(ErrorLoggerService $errorLoggerService, Level $level = Level::Error, bool $bubble = true): DatabaseErrorLogHandler
    {
        return new DatabaseErrorLogHandler($errorLoggerService, $level, $bubble);
    }

    public function testWriteLogsRecordWithoutException(): void
    {
        $errorLoggerService = $this->createMock(ErrorLoggerService::class);
        $handler = $this->createHandler($errorLoggerService);
        
        $record = new LogRecord(
            \DateTimeImmutable::createFromFormat('U', (string)\time()),
            'test.channel',
            Level::Info,
            'Test message without exception.',
            [], // context
            [], // extra
            'formatted log message', // formatted
        );

        $expectedContext = ['formatted_log' => 'formatted log message'];

        $errorLoggerService->expects($this->once())
            ->method('log')
            ->with(
                'INFO',
                'Test message without exception.',
                $expectedContext,
            );

        // Access protected method via reflection
        $method = new \ReflectionMethod($handler, 'write');
        $method->invoke($handler, $record);
        
        // ✅ Add assertion
        self::assertTrue(true);
    }

    public function testWriteLogsRecordWithException(): void
    {
        $errorLoggerService = $this->createMock(ErrorLoggerService::class);
        $handler = $this->createHandler($errorLoggerService);
        
        $exception = new RuntimeException('Something went wrong.', 500);
        $record = new LogRecord(
            \DateTimeImmutable::createFromFormat('U', (string)\time()),
            'test.channel',
            Level::Error,
            'Test message with exception.',
            ['exception' => $exception], // context with exception
            [], // extra
            'formatted log message', // formatted
        );

        $expectedContext = [
            'exception' => $exception,
            'formatted_log' => 'formatted log message',
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
        ];

        $errorLoggerService->expects($this->once())
            ->method('log')
            ->with(
                'ERROR',
                'Test message with exception.',
                $expectedContext,
            );

        // Access protected method via reflection
        $method = new \ReflectionMethod($handler, 'write');
        $method->invoke($handler, $record);
        
        // ✅ Add assertion
        self::assertTrue(true);
    }

    public function testWriteLogsRecordWithoutFormattedLog(): void
    {
        $errorLoggerService = $this->createMock(ErrorLoggerService::class);
        $handler = $this->createHandler($errorLoggerService);
        
        $record = new LogRecord(
            \DateTimeImmutable::createFromFormat('U', (string)\time()),
            'test.channel',
            Level::Warning,
            'Test message without formatted log.',
            [], // context
            [], // extra
            null, // formatted
        );

        $expectedContext = [];

        $errorLoggerService->expects($this->once())
            ->method('log')
            ->with(
                'WARNING',
                'Test message without formatted log.',
                $expectedContext,
            );

        // Access protected method via reflection
        $method = new \ReflectionMethod($handler, 'write');
        $method->invoke($handler, $record);
        
        // ✅ Add assertion
        self::assertTrue(true);
    }
}