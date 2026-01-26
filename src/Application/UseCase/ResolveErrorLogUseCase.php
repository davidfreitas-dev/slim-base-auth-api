<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Repository\ErrorLogRepositoryInterface;

class ResolveErrorLogUseCase
{
    public function __construct(
        private readonly ErrorLogRepositoryInterface $errorLogRepository,
    ) {
    }

    /**
     * Marks an error log as resolved.
     *
     * @param int $errorLogId       The ID of the error log to mark as resolved.
     * @param int $resolvedByUserId The ID of the user who resolved the error.
     *
     * @return bool True if the error log was marked as resolved, false otherwise.
     */
    public function execute(int $errorLogId, int $resolvedByUserId): bool
    {
        return $this->errorLogRepository->markAsResolved($errorLogId, $resolvedByUserId);
    }
}
