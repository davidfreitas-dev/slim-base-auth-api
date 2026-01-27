<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Admin;

use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj; 
use Tests\Functional\FunctionalTestCase;
use Fig\Http\Message\StatusCodeInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use Faker\Factory;

class GetUserTest extends FunctionalTestCase
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

    private function createTestUser(string $email, string $cpfcnpj): User
    {
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $person = new Person(
            name: 'Test User',
            email: $email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
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
    
    public function testGetUserByIdAsAdminReturnsUser(): void
    {
        // Arrange
        $testEmail = 'user_to_get@example.com';
        $this->createTestUser($testEmail, '61696681057');

        // Get the actual user from database by email to ensure we have the correct ID
        $user = $this->userRepository->findByEmail($testEmail);
        $this->assertNotNull($user, 'User should exist in database');

        // Act
        $response = $this->sendRequest('GET', '/api/v1/users/' . $user->getId(), [], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $body['status']);
        $this->assertEquals($user->getId(), $body['data']['id']);
        $this->assertEquals($user->getPerson()->getName(), $body['data']['name']);
        $this->assertEquals($user->getPerson()->getEmail(), $body['data']['email']);
        $this->assertEquals($user->getRole()->getName(), $body['data']['role_name']);
        $this->assertEquals($user->isActive(), $body['data']['is_active']);
        $this->assertEquals($user->isVerified(), $body['data']['is_verified']);
    }
}