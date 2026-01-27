<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\ErrorLog;
use App\Domain\Repository\ErrorLogRepositoryInterface;
use DateTimeImmutable;
use PDO;

class DatabaseErrorLogRepository implements ErrorLogRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ErrorLog $errorLog): ErrorLog
    {
        $sql = 'INSERT INTO error_logs (severity, message, context, created_at, resolved_at, resolved_by)
                VALUES (:severity, :message, :context, :created_at, :resolved_at, :resolved_by)';

        $stmt = $this->pdo->prepare($sql);

        $contextJson = \json_encode($errorLog->getContext());
        if ($contextJson === false) {
            // Handle JSON encoding error, perhaps log it elsewhere or throw an exception
            $contextJson = '[]';
        }

        $stmt->execute([
            'severity' => $errorLog->getSeverity(),
            'message' => $errorLog->getMessage(),
            'context' => $contextJson,
            'created_at' => $errorLog->getCreatedAt()->format('Y-m-d H:i:s'),
            'resolved_at' => $errorLog->getResolvedAt()?->format('Y-m-d H:i:s'),
            'resolved_by' => $errorLog->getResolvedBy(),
        ]);

        $errorLog->setId((int)$this->pdo->lastInsertId());

        return $errorLog;
    }

    public function findById(int $id): ?ErrorLog
    {
        $sql = 'SELECT id, severity, message, context, created_at, resolved_at, resolved_by FROM error_logs WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $context = \json_decode((string) $data['context'], true);
        if ($context === null && \json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decoding error
            $context = [];
        }

        return new ErrorLog(
            severity: $data['severity'],
            message: $data['message'],
            context: $context,
            createdAt: new DateTimeImmutable($data['created_at']),
            resolvedAt: $data['resolved_at'] ? new DateTimeImmutable($data['resolved_at']) : null,
            resolvedBy: $data['resolved_by'],
            id: (int)$data['id'],
        );
    }

    public function findAll(int $page, int $perPage, ?string $severity, ?bool $resolved): array
    {
        $sql = 'SELECT id, severity, message, context, created_at, resolved_at, resolved_by FROM error_logs WHERE 1=1';
        $params = [];

        if ($severity !== null) {
            $sql .= ' AND severity = :severity';
            $params[':severity'] = $severity;
        }

        if ($resolved !== null) {
            if ($resolved) {
                $sql .= ' AND resolved_at IS NOT NULL';
            } else {
                $sql .= ' AND resolved_at IS NULL';
            }
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->pdo->prepare($sql);

        // Bind parameters manually to handle different types for limit/offset
        foreach ($params as $key => $value) {
            if (\in_array($key, [':limit', ':offset'], true)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        $errorLogs = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $context = \json_decode((string) $data['context'], true);
            if ($context === null && \json_last_error() !== JSON_ERROR_NONE) {
                $context = [];
            }

            $errorLogs[] = new ErrorLog(
                severity: $data['severity'],
                message: $data['message'],
                context: $context,
                createdAt: new DateTimeImmutable($data['created_at']),
                resolvedAt: $data['resolved_at'] ? new DateTimeImmutable($data['resolved_at']) : null,
                resolvedBy: $data['resolved_by'],
                id: (int)$data['id'],
            );
        }

        return $errorLogs;
    }

    public function countAll(?string $severity, ?bool $resolved): int
    {
        $sql = 'SELECT COUNT(id) FROM error_logs WHERE 1=1';
        $params = [];

        if ($severity !== null) {
            $sql .= ' AND severity = :severity';
            $params[':severity'] = $severity;
        }

        if ($resolved !== null) {
            if ($resolved) {
                $sql .= ' AND resolved_at IS NOT NULL';
            } else {
                $sql .= ' AND resolved_at IS NULL';
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function markAsResolved(int $id, int $resolvedByUserId): bool
    {
        $sql = 'UPDATE error_logs SET resolved_at = NOW(), resolved_by = :resolved_by WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'resolved_by' => $resolvedByUserId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }
}
