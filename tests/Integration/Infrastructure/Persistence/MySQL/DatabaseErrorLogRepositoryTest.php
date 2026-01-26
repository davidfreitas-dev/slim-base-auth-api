<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\MySQL;

use Faker\Factory;
use DateTimeImmutable;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\Entity\ErrorLog;
use App\Domain\ValueObject\CpfCnpj;
use Tests\Integration\DatabaseTestCase;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use App\Infrastructure\Persistence\MySQL\DatabaseErrorLogRepository;

class DatabaseErrorLogRepositoryTest extends DatabaseTestCase
{
    private DatabaseErrorLogRepository $errorLogRepository;
    private UserRepository $userRepository;
    private PersonRepository $personRepository;
    private RoleRepository $roleRepository;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorLogRepository = new DatabaseErrorLogRepository(self::$pdo);
        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo, $this->personRepository, $this->roleRepository);
        $this->faker = Factory::create('pt_BR');
    }

    private function createTestUser(int $roleId = 2): User
    {
        $person = new Person(
            name: $this->faker->name,
            email: $this->faker->email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $this->personRepository->create($person);

        $role = $this->roleRepository->findById($roleId);
        if (!$role instanceof Role) {
            throw new \App\Domain\Exception\NotFoundException(
                "Role with ID {$roleId} not found. Make sure it is seeded."
            );
        }

        $user = new User(
            person: $person,
            password: 'password123',
            role: $role
        );
        return $this->userRepository->create($user);
    }

    private function createTestErrorLog(string $severity = 'ERROR', ?User $resolvedBy = null): ErrorLog
    {
        $errorLog = new ErrorLog(
            severity: $severity,
            message: $this->faker->sentence,
            context: ['file' => $this->faker->filePath(), 'line' => $this->faker->randomNumber()],
            createdAt: new DateTimeImmutable(),
            resolvedAt: $resolvedBy ? new DateTimeImmutable() : null,
            resolvedBy: $resolvedBy?->getId()
        );

        return $this->errorLogRepository->save($errorLog);
    }

    public function testSaveAndFindById(): void
    {
        $createdErrorLog = $this->createTestErrorLog();

        $this->assertNotNull($createdErrorLog->getId(), 'ErrorLog ID should not be null after saving');

        $foundErrorLog = $this->errorLogRepository->findById($createdErrorLog->getId());

        $this->assertNotNull($foundErrorLog, 'Should find an error log by ID');
        $this->assertEquals($createdErrorLog->getId(), $foundErrorLog->getId());
        $this->assertEquals($createdErrorLog->getMessage(), $foundErrorLog->getMessage());
        $this->assertEquals($createdErrorLog->getSeverity(), $foundErrorLog->getSeverity());
    }

    public function testMarkAsResolved(): void
    {
        $user = $this->createTestUser();
        $errorLog = $this->createTestErrorLog();

        $resolved = $this->errorLogRepository->markAsResolved($errorLog->getId(), $user->getId());

        $this->assertTrue($resolved, 'markAsResolved should return true on success');

        $resolvedErrorLog = $this->errorLogRepository->findById($errorLog->getId());

        $this->assertNotNull($resolvedErrorLog->getResolvedAt(), 'ResolvedAt should be set');
        $this->assertEquals($user->getId(), $resolvedErrorLog->getResolvedBy());
    }

    public function testFindAll(): void
    {
        $this->createTestErrorLog();
        $this->createTestErrorLog();

        $errorLogs = $this->errorLogRepository->findAll(1, 10, null, null);

        $this->assertCount(2, $errorLogs);
    }

    public function testFindAllWithSeverityFilter(): void
    {
        $this->createTestErrorLog('CRITICAL');
        $this->createTestErrorLog('ERROR');

        $errorLogs = $this->errorLogRepository->findAll(1, 10, 'CRITICAL', null);

        $this->assertCount(1, $errorLogs);
        $this->assertEquals('CRITICAL', $errorLogs[0]->getSeverity());
    }

    public function testFindAllWithResolvedFilter(): void
    {
        $user = $this->createTestUser();
        $this->createTestErrorLog('ERROR', $user);
        $this->createTestErrorLog('ERROR');

        // Test for resolved logs
        $resolvedLogs = $this->errorLogRepository->findAll(1, 10, null, true);
        $this->assertCount(1, $resolvedLogs);
        $this->assertNotNull($resolvedLogs[0]->getResolvedAt());

        // Test for unresolved logs
        $unresolvedLogs = $this->errorLogRepository->findAll(1, 10, null, false);
        $this->assertCount(1, $unresolvedLogs);
        $this->assertNull($unresolvedLogs[0]->getResolvedAt());
    }
}
