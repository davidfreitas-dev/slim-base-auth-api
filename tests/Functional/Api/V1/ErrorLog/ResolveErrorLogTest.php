<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1;

use App\Domain\Entity\ErrorLog;
use App\Domain\Entity\Person;
use App\Domain\Entity\User;
use App\Domain\Repository\ErrorLogRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Tests\Functional\FunctionalTestCase;

class ResolveErrorLogTest extends FunctionalTestCase
{
    protected User $adminUser;
    protected string $adminToken;
    protected User $regularUser;
    protected string $regularUserToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAdminUser();
        $this->adminToken = $this->generateTokenForUser($this->adminUser);

        $this->createRegularUser();
        $this->regularUserToken = $this->generateTokenForUser($this->regularUser);

        $this->createErrorLogs();
    }

    private function createAdminUser(): void
    {
        $personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);

        $adminRole = $roleRepository->findByName('admin');
        if (!$adminRole) {
            $this->fail('"admin" role not found in the database.');
        }

        $person = new Person(name: 'Admin User', email: 'admin@test.com');
        $person = $personRepository->create($person);

        $this->adminUser = new User(
            person: $person,
            password: password_hash('password', PASSWORD_DEFAULT),
            role: $adminRole
        );
        $this->adminUser->markAsVerified();

        $userRepository->create($this->adminUser);
    }

    private function createRegularUser(): void
    {
        $personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);

        $userRole = $roleRepository->findByName('user');
        if (!$userRole) {
            $this->fail('"user" role not found in the database.');
        }

        $person = new Person(name: 'Regular User', email: 'user@test.com');
        $person = $personRepository->create($person);

        $this->regularUser = new User(
            person: $person,
            password: password_hash('password', PASSWORD_DEFAULT),
            role: $userRole
        );
        $this->regularUser->markAsVerified();

        $userRepository->create($this->regularUser);
    }

    protected function createErrorLogs(): void
    {
        $errorLogRepository = $this->app->getContainer()->get(ErrorLogRepositoryInterface::class);

        // Explicitly set createdAt to ensure order
        $log1 = new ErrorLog(
            severity: 'CRITICAL',
            message: 'Critical error 1',
            context: ['detail' => 'foo'],
            createdAt: new DateTimeImmutable('-1 minute'),
        );
        $errorLogRepository->save($log1);

        $log2 = new ErrorLog(
            severity: 'ERROR',
            message: 'Error 2',
            context: ['detail' => 'bar'],
            createdAt: new DateTimeImmutable(),
        );
        $errorLogRepository->save($log2);
    }

    public function testResolveErrorLogAsAdmin(): void
    {
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/error-logs/1/resolve',
            headers: ['Authorization' => 'Bearer ' . $this->adminToken]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayNotHasKey('data', $body);

        // Verify it is resolved
        $errorLogRepo = $this->app->getContainer()->get(ErrorLogRepositoryInterface::class);
        $errorLog = $errorLogRepo->findById(1);
        $this->assertNotNull($errorLog->getResolvedAt());
    }

    public function testResolveErrorLogNotFound(): void
    {
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/error-logs/999/resolve',
            headers: ['Authorization' => 'Bearer ' . $this->adminToken]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResolveErrorLogAsUserFails(): void
    {
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/error-logs/1/resolve',
            headers: ['Authorization' => 'Bearer ' . $this->regularUserToken]
        );

        $this->assertEquals(403, $response->getStatusCode());
    }
}
