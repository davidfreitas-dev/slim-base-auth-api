<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ErrorLog;

interface ErrorLogRepositoryInterface
{
    /**
     * Saves an error log entry to the repository.
     *
     * @param ErrorLog $errorLog The error log entity to save.
     *
     * @return ErrorLog The saved error log entity, possibly with an updated ID.
     */
    public function save(ErrorLog $errorLog): ErrorLog;

    /**
     * Finds an error log entry by its ID.
     *
     * @param int $id The ID of the error log.
     *
     * @return ErrorLog|null The found error log entity or null if not found.
     */
    public function findById(int $id): ?ErrorLog;

    /**
     * Lists error logs with pagination and optional filtering.
     *
     * @param int         $page     The current page number.
     * @param int         $perPage  The number of items per page.
     * @param string|null $severity Optional: Filter by severity level (e.g., 'ERROR', 'CRITICAL').
     * @param bool|null   $resolved Optional: Filter by resolution status (true for resolved, false for unresolved).
     *
     * @return array An array of ErrorLog entities.
     */
    public function findAll(int $page, int $perPage, ?string $severity, ?bool $resolved): array;

    /**
     * Counts all error logs based on optional filters.
     *
     * @param string|null $severity Optional: Filter by severity level (e.g., 'ERROR', 'CRITICAL').
     * @param bool|null   $resolved Optional: Filter by resolution status (true for resolved, false for unresolved).
     *
     * @return int The total number of error logs matching the criteria.
     */
    public function countAll(?string $severity, ?bool $resolved): int;

    /**
     * Marks an error log as resolved.
     *
     * @param int $id               The ID of the error log to mark as resolved.
     * @param int $resolvedByUserId The ID of the user who resolved the error.
     *
     * @return bool True if the error log was marked as resolved, false otherwise.
     */
    public function markAsResolved(int $id, int $resolvedByUserId): bool;
}
