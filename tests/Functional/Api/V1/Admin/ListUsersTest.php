<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Admin;

use App\Domain\Entity\Person;
use App\Domain\Entity\User;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj; 
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;
use Faker\Factory;

class ListUsersTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private string $adminToken;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
        $this->adminToken = $this->getAdminToken();
    }

    private function getAdminToken(): string
    {
        // Create admin user
        $password = 'adminpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $person = new Person(
            name: 'Admin User',
            email: 'admin@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = $this->roleRepository->findByName('admin');

        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );
        $this->userRepository->create($user);

        // Login as admin
        $response = $this->sendRequest('POST', '/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => $password,
        ]);

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        if (!isset($body['data']['access_token'])) {
            throw new \RuntimeException(
                "Failed to get admin token. Response: " . json_encode($body)
            );
        }

        return $body['data']['access_token'];
    }

    public function testListUsersAsAdminReturnsUsers(): void
    {
        // Act
        $response = $this->sendRequest('GET', '/api/v1/users', [], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $body['status']);
        $this->assertIsArray($body['data']);
        $this->assertGreaterThan(0, count($body['data']));
    }
}