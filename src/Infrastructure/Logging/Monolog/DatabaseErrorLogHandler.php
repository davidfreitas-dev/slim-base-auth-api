<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging\Monolog;

use App\Application\Service\ErrorLoggerService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class DatabaseErrorLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly ErrorLoggerService $errorLoggerService,
        \Monolog\Level|int $level = \Monolog\Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Writes the record down to the database.
     *
     * @param LogRecord $record
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        // Monolog's $record->level->getName() maps directly to our 'severity'
        $severity = $record->level->getName();
        $message = $record->message;
        $context = $record->context; // Monolog context can include exception, etc.

        // Add formatted trace if available
        if ($record->formatted !== null) {
            $context['formatted_log'] = $record->formatted;
        }

        // Add exception details if available in context
        if (isset($record->context['exception']) && $record->context['exception'] instanceof Throwable) {
            $exception = $record->context['exception'];
            $context['exception_class'] = $exception::class;
            $context['exception_message'] = $exception->getMessage();
            $context['exception_code'] = $exception->getCode();
            $context['exception_file'] = $exception->getFile();
            $context['exception_line'] = $exception->getLine();
            $context['exception_trace'] = $exception->getTraceAsString();
        }

        $this->errorLoggerService->log($severity, $message, $context);
    }
}
