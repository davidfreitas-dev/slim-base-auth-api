<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Entity\ErrorLog;
use App\Domain\Repository\ErrorLogRepositoryInterface;

class ListErrorLogsUseCase
{
    public function __construct(
        private readonly ErrorLogRepositoryInterface $errorLogRepository,
    ) {
    }

    /**
     * Lists error logs with pagination and optional filtering.
     *
     * @param int         $page     The current page number.
     * @param int         $perPage  The number of items per page.
     * @param string|null $severity Optional: Filter by severity level (e.g., 'ERROR', 'CRITICAL').
     * @param bool|null   $resolved Optional: Filter by resolution status (true for resolved, false for unresolved).
     *
     * @return array A list of ErrorLog entities.
     */
    public function execute(int $page, int $perPage, ?string $severity, ?bool $resolved): array
    {
        // For simplicity, the repository method will handle the filtering and pagination.
        // In a more complex scenario, you might have a dedicated specification or query object.
        return $this->errorLogRepository->findAll($page, $perPage, $severity, $resolved);
    }
}
