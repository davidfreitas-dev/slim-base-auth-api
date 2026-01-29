<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\ErrorLogListResponseDTO;
use App\Application\DTO\ErrorLogResponseDTO;
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
     * @return ErrorLogListResponseDTO A list of ErrorLog entities.
     */
    public function execute(int $page, int $perPage, ?string $severity, ?bool $resolved): ErrorLogListResponseDTO
    {
        // For simplicity, the repository method will handle the filtering and pagination.
        // In a more complex scenario, you might have a dedicated specification or query object.
        $errorLogs = $this->errorLogRepository->findAll($page, $perPage, $severity, $resolved);
        $total = $this->errorLogRepository->countAll($severity, $resolved); // Assuming a count method exists

        $errorLogDTOs = \array_map(
            static fn (ErrorLog $errorLog): ErrorLogResponseDTO => new ErrorLogResponseDTO(
                id: $errorLog->getId(),
                severity: $errorLog->getSeverity(),
                message: $errorLog->getMessage(),
                context: $errorLog->getContext(),
                resolvedAt: $errorLog->getResolvedAt()?->format(\DateTimeImmutable::ATOM),
                resolvedByUserId: $errorLog->getResolvedBy(),
                createdAt: $errorLog->getCreatedAt(),
            ),
            $errorLogs,
        );

        return new ErrorLogListResponseDTO(
            errorLogs: $errorLogDTOs,
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }
}
