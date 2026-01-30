<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use Faker\Factory;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\ValueObject\CpfCnpj; 
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;

class LogoutTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
    }

    public function testLogoutWithValidTokenReturnsOk(): void
    {
        // Arrange
        $email = 'test@example.com';
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $person = new Person(
            name: 'testuser',
            email: $email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = $this->roleRepository->findByName('user');
        if (!$role instanceof Role) {
            throw new NotFoundException("Role 'user' not found in the database. Please ensure roles are seeded for testing.");
        }

        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );

        $this->userRepository->create($user);

        // Login to get tokens
        $loginResponse = $this->sendRequest('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $loginResponse->getBody()->rewind();
        $loginBody = $loginResponse->getBody()->getContents();
        $loginData = json_decode($loginBody, true);
        $accessToken = $loginData['data']['access_token'];

        // Act
        $response = $this->sendRequest(
            'POST',
            '/api/v1/auth/logout',
            [],
            ['Authorization' => 'Bearer ' . $accessToken]
        );
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $responseData = json_decode($body, true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Logout bem-sucedido', $responseData['message']);
    }

    public function testLogoutWithoutTokenReturnsUnauthorized(): void
    {
        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/logout');

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}
