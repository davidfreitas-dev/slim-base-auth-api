<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\ErrorLog;
use App\Domain\Repository\ErrorLogRepositoryInterface;
use DateTimeImmutable;

class ErrorLoggerService
{
    public function __construct(
        private readonly ErrorLogRepositoryInterface $errorLogRepository,
    ) {
    }

    /**
     * Logs a critical error to the database.
     *
     * @param string   $severity   The severity level of the error (e.g., 'CRITICAL', 'ERROR').
     * @param string   $message    The error message.
     * @param array    $context    Additional context for the error (e.g., stack trace, user ID, request data).
     * @param int|null $resolvedBy Optional user ID if the error is immediately resolved.
     *
     * @return ErrorLog The saved ErrorLog entity.
     */
    public function log(
        string $severity,
        string $message,
        array $context = [],
        ?int $resolvedBy = null,
    ): ErrorLog {
        $errorLog = new ErrorLog(
            severity: $severity,
            message: $message,
            context: $context,
            resolvedAt: $resolvedBy ? new DateTimeImmutable() : null,
            resolvedBy: $resolvedBy,
        );

        return $this->errorLogRepository->save($errorLog);
    }

    /**
     * Marks an existing error log as resolved.
     *
     * @param int $errorLogId       The ID of the error log to mark as resolved.
     * @param int $resolvedByUserId The ID of the user who resolved the error.
     *
     * @return bool True if the error log was marked as resolved, false otherwise.
     */
    public function markAsResolved(int $errorLogId, int $resolvedByUserId): bool
    {
        return $this->errorLogRepository->markAsResolved($errorLogId, $resolvedByUserId);
    }
}
