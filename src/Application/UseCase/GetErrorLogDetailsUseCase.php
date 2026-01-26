<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Entity\ErrorLog;
use App\Domain\Repository\ErrorLogRepositoryInterface;

class GetErrorLogDetailsUseCase
{
    public function __construct(
        private readonly ErrorLogRepositoryInterface $errorLogRepository,
    ) {
    }

    /**
     * Gets details of a single error log by its ID.
     *
     * @param int $errorLogId The ID of the error log.
     *
     * @return ErrorLog|null The ErrorLog entity or null if not found.
     */
    public function execute(int $errorLogId): ?ErrorLog
    {
        return $this->errorLogRepository->findById($errorLogId);
    }
}
