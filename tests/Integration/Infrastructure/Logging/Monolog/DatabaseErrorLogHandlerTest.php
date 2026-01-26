<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Logging\Monolog;

use App\Application\Service\ErrorLoggerService;
use App\Infrastructure\Logging\Monolog\DatabaseErrorLogHandler;
use App\Infrastructure\Persistence\MySQL\DatabaseErrorLogRepository;
use Monolog\Logger;
use Tests\Integration\DatabaseTestCase;

class DatabaseErrorLogHandlerTest extends DatabaseTestCase
{
    private DatabaseErrorLogRepository $errorLogRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorLogRepository = new DatabaseErrorLogRepository(self::$pdo);
    }

    public function testWrite(): void
    {
        $errorLoggerService = new ErrorLoggerService($this->errorLogRepository);
        $handler = new DatabaseErrorLogHandler($errorLoggerService);

        $logger = new Logger('test-logger');
        $logger->pushHandler($handler);

        $message = 'This is a test error message';
        $context = ['test' => 'context'];

        $logger->error($message, $context);

        // Verify that the log was written to the database
        $logs = $this->errorLogRepository->findAll(1, 10, null, null);

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertEquals('ERROR', $log->getSeverity());
        $this->assertEquals($message, $log->getMessage());
        $this->assertArrayHasKey('test', $log->getContext());
        $this->assertEquals('context', $log->getContext()['test']);
    }

    public function testWriteWithException(): void
    {
        $errorLoggerService = new ErrorLoggerService($this->errorLogRepository);
        $handler = new DatabaseErrorLogHandler($errorLoggerService);

        $logger = new Logger('test-logger');
        $logger->pushHandler($handler);

        $message = 'This is a test error with exception';
        $exception = new \RuntimeException('Test exception', 123);

        $logger->error($message, ['exception' => $exception]);

        // Verify that the log was written to the database
        $logs = $this->errorLogRepository->findAll(1, 10, null, null);

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertEquals('ERROR', $log->getSeverity());
        $this->assertEquals($message, $log->getMessage());
        
        $logContext = $log->getContext();
        $this->assertEquals('RuntimeException', $logContext['exception_class']);
        $this->assertEquals('Test exception', $logContext['exception_message']);
        $this->assertEquals(123, $logContext['exception_code']);
        $this->assertStringContainsString('DatabaseErrorLogHandlerTest.php', $logContext['exception_file']);
    }
}
