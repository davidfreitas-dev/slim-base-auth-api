<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\ErrorLog;
use App\Infrastructure\Persistence\MySQL\DatabaseErrorLogRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Persistence\MySQL\DatabaseErrorLogRepository
 */
final class DatabaseErrorLogRepositoryTest extends TestCase
{
    private function createErrorLog(int $id = 0): ErrorLog
    {
        return new ErrorLog(
            severity: 'ERROR',
            message: 'Test error message',
            context: ['exception' => 'RuntimeException', 'code' => 500],
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00'),
            id: $id,
        );
    }

    public function testSave(): void
    {
        $errorLog = $this->createErrorLog();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO error_logs'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($errorLog) {
                self::assertArrayHasKey('severity', $params);
                self::assertSame($errorLog->getSeverity(), $params['severity']);
                self::assertArrayHasKey('message', $params);
                self::assertSame($errorLog->getMessage(), $params['message']);
                self::assertArrayHasKey('context', $params);
                self::assertJson((string)$params['context']);
                self::assertArrayHasKey('created_at', $params);
                self::assertSame($errorLog->getCreatedAt()->format('Y-m-d H:i:s'), $params['created_at']);
                self::assertArrayHasKey('resolved_at', $params);
                self::assertNull($params['resolved_at']);
                self::assertArrayHasKey('resolved_by', $params);
                self::assertNull($params['resolved_by']);

                return true;
            }))
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1');

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->save($errorLog);

        self::assertEquals(1, $result->getId());
        self::assertEquals($errorLog, $result);
    }

    public function testFindByIdFound(): void
    {
        $errorLogData = [
            'id' => 1,
            'severity' => 'ERROR',
            'message' => 'Test error message',
            'context' => \json_encode(['exception' => 'RuntimeException']),
            'created_at' => '2023-01-01 10:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
        ];
        
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, severity, message, context, created_at, resolved_at, resolved_by FROM error_logs WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($errorLogData);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->findById(1);

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
        self::assertSame('ERROR', $result->getSeverity());
        self::assertSame('Test error message', $result->getMessage());
        self::assertEquals(['exception' => 'RuntimeException'], $result->getContext());
        self::assertEquals(new DateTimeImmutable('2023-01-01 10:00:00'), $result->getCreatedAt());
        self::assertNull($result->getResolvedAt());
        self::assertNull($result->getResolvedBy());
    }

    public function testFindByIdNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->findById(1);

        self::assertNull($result);
    }

    public function testFindByIdWithInvalidJsonContext(): void
    {
        $errorLogData = [
            'id' => 1,
            'severity' => 'ERROR',
            'message' => 'Test error message',
            'context' => '{"invalid json"', // Invalid JSON string
            'created_at' => '2023-01-01 10:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
        ];
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->method('prepare')
            ->willReturn($stmt);

        $stmt->method('execute')
            ->willReturn(true);

        $stmt->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($errorLogData);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->findById(1);

        self::assertNotNull($result);
        self::assertEquals(1, $result->getId());
        self::assertEmpty($result->getContext()); // Context should be an empty array due to decoding error
    }

    public function testFindAllWithNoFilters(): void
    {
        $errorLogData = [
            'id' => 1,
            'severity' => 'ERROR',
            'message' => 'Test message',
            'context' => \json_encode(['foo' => 'bar']),
            'created_at' => '2023-01-01 12:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
        ];
        
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, severity, message, context, created_at, resolved_at, resolved_by FROM error_logs WHERE 1=1 ORDER BY created_at DESC LIMIT :limit OFFSET :offset'))
            ->willReturn($stmt);

        $stmt->expects($this->exactly(2)) // For :limit and :offset
            ->method('bindValue')
            ->willReturnOnConsecutiveCalls(true, true);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($errorLogData, false);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->findAll(1, 20, null, null);

        self::assertCount(1, $result);
        self::assertEquals(1, $result[0]->getId());
    }

    public function testFindAllWithInvalidJsonContext(): void
    {
        $errorLogData1 = [
            'id' => 1,
            'severity' => 'ERROR',
            'message' => 'Valid message',
            'context' => \json_encode(['valid' => 'context']),
            'created_at' => '2023-01-01 12:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
        ];
        $errorLogData2 = [
            'id' => 2,
            'severity' => 'WARNING',
            'message' => 'Invalid message',
            'context' => '{"invalid json"', // Invalid JSON string
            'created_at' => '2023-01-01 13:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
        ];
        
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->method('prepare')
            ->willReturn($stmt);

        $stmt->method('bindValue')
            ->willReturnOnConsecutiveCalls(true, true);

        $stmt->method('execute')
            ->willReturn(true);

        $stmt->method('fetch')
            ->willReturnOnConsecutiveCalls($errorLogData1, $errorLogData2, false);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->findAll(1, 20, null, null);

        self::assertCount(2, $result);
        self::assertEquals(['valid' => 'context'], $result[0]->getContext());
        self::assertEmpty($result[1]->getContext()); // Context should be an empty array due to decoding error
    }

    public function testFindAllWithSeverityFilter(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE 1=1 AND severity = :severity'))
            ->willReturn($stmt);

        $stmt->expects($this->exactly(3)) // Expect three bindValue calls
            ->method('bindValue')
            ->willReturnOnConsecutiveCalls(true, true, true);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())->method('fetch')->willReturn(false);

        $repository = new DatabaseErrorLogRepository($pdo);
        $repository->findAll(1, 10, 'WARNING', null);
        
        self::assertTrue(true);
    }

    public function testFindAllWithResolvedFilter(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE 1=1 AND resolved_at IS NOT NULL'))
            ->willReturn($stmt);

        $stmt->expects($this->exactly(2)) // for limit and offset
            ->method('bindValue')
            ->willReturnOnConsecutiveCalls(true, true);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
            
        $stmt->expects($this->once())->method('fetch')->willReturn(false);

        $repository = new DatabaseErrorLogRepository($pdo);
        $repository->findAll(1, 10, null, true);
        
        self::assertTrue(true);
    }

    public function testCountAllWithNoFilters(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT COUNT(id) FROM error_logs WHERE 1=1'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(5);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->countAll(null, null);

        self::assertEquals(5, $result);
    }

    public function testCountAllWithSeverityFilter(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE 1=1 AND severity = :severity'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([':severity' => 'CRITICAL'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(2);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->countAll('CRITICAL', null);

        self::assertEquals(2, $result);
    }

    public function testCountAllWithResolvedFilter(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE 1=1 AND resolved_at IS NULL'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(3);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->countAll(null, false);

        self::assertEquals(3, $result);
    }

    public function testMarkAsResolved(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE error_logs SET resolved_at = NOW(), resolved_by = :resolved_by WHERE id = :id'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['resolved_by' => 123, 'id' => 1])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->markAsResolved(1, 123);

        self::assertTrue($result);
    }

    public function testMarkAsResolvedNotFound(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $repository = new DatabaseErrorLogRepository($pdo);
        $result = $repository->markAsResolved(1, 123);

        self::assertFalse($result);
    }
}