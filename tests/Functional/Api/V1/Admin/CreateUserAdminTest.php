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

class CreateUserAdminTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private \Faker\Generator $faker;
    private string $adminToken;

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

    private function getUserToken(): string
    {
        $testEmail = 'regularuser@example.com';
        $this->createTestUser($testEmail, '51210471000');

        // Get the actual user from database
        $user = $this->userRepository->findByEmail($testEmail);

        // Login as user
        $response = $this->sendRequest('POST', '/api/v1/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'password123',
        ]);

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        return $body['data']['access_token'];
    }

    private function createTestUser(string $email, string $cpfcnpj): User
    {
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $person = new Person(
            name: 'Test User',
            email: $email,
            cpfcnpj: CpfCnpj::fromString($cpfcnpj) // Convert string to CpfCnpj object
        );
        $person = $this->personRepository->create($person);

        $role = $this->roleRepository->findByName('user');

        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );

        return $this->userRepository->create($user);
    }
    
    public function testCreateUserAsAdminReturnsCreated(): void
    {
        // Arrange
        $payload = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'cpfcnpj' => '74215287090',
            'role' => 'user',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/users', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_CREATED, $response->getStatusCode());
        $this->assertEquals('success', $body['status']);
        $this->assertEquals('User created successfully.', $body['message']);
        $this->assertEquals($payload['email'], $body['data']['email']);
        $this->assertEquals($payload['name'], $body['data']['name']);
    }

    public function testCreateUserAsNonAdminReturnsForbidden(): void
    {
        // Arrange
        $userToken = $this->getUserToken();
        $payload = [
            'name' => 'New User by non-admin',
            'email' => 'newuserbynonadmin@example.com',
            'password' => 'password123',
            'cpfcnpj' => '61696681057',
            'role' => 'user',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/users', $payload, [
            'Authorization' => 'Bearer ' . $userToken,
        ]);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_FORBIDDEN, $response->getStatusCode());
    }
}