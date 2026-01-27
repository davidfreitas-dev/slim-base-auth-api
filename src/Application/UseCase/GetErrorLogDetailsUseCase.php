<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\ErrorLogResponseDTO;
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
     * @return ErrorLogResponseDTO|null The ErrorLog entity or null if not found.
     */
    public function execute(int $errorLogId): ?ErrorLogResponseDTO
    {
        $errorLog = $this->errorLogRepository->findById($errorLogId);

        if (!$errorLog instanceof ErrorLog) {
            return null;
        }

        return new ErrorLogResponseDTO(
            id: $errorLog->getId(),
            severity: $errorLog->getSeverity(),
            message: $errorLog->getMessage(),
            context: $errorLog->getContext(),
            resolvedAt: $errorLog->getResolvedAt()?->format(\DateTimeImmutable::ATOM),
            resolvedByUserId: $errorLog->getResolvedBy(),
            createdAt: $errorLog->getCreatedAt(),
        );
    }
}
