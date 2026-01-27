<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use Faker\Factory;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use Tests\Functional\FunctionalTestCase;
use Fig\Http\Message\StatusCodeInterface;

class RefreshTest extends FunctionalTestCase
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

    public function testRefreshWithValidTokenReturnsNewTokens(): void
    {
        // Arrange
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Criar e salvar Person primeiro
        $person = new Person(
            name: 'testuser',
            email: 'test@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        // Criar Role
        $role = $this->roleRepository->findByName('user');
        if (!$role instanceof Role) {
            throw new NotFoundException("Role 'user' not found in the database. Please ensure roles are seeded for testing.");
        }

        // Criar User
        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: false
        );

        $user->activate();

        $this->userRepository->create($user);

        // Fazer login para obter o refresh token
        $loginPayload = [
            'email' => 'test@example.com',
            'password' => $password,
        ];

        $loginResponse = $this->sendRequest('POST', '/api/v1/auth/login', $loginPayload);
        $loginResponse->getBody()->rewind(); // Resetar o ponteiro do stream
        $loginBody = $loginResponse->getBody()->getContents();
        $loginResponseData = json_decode($loginBody, true);

        // Debug do login
        if ($loginResponse->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            $this->fail(sprintf('Login failed with status %s: %s', $loginResponse->getStatusCode(), $loginBody));
        }

        $this->assertNotNull($loginResponseData, 'Login response is not valid JSON: ' . $loginBody);
        $this->assertArrayHasKey('data', $loginResponseData, "Login response missing 'data' key");
        $this->assertArrayHasKey('access_token', $loginResponseData['data'], "Login response missing 'access_token' key");
        $this->assertArrayHasKey('refresh_token', $loginResponseData['data'], "Login response missing 'refresh_token' key");

        $refreshToken = $loginResponseData['data']['refresh_token'];

        $refreshPayload = [
            'refresh_token' => $refreshToken,
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/refresh', $refreshPayload);
        $response->getBody()->rewind(); // Resetar o ponteiro do stream
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Debug em caso de falha
        if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            $this->fail(sprintf('Refresh failed with status %s: %s', $response->getStatusCode(), $body));
        }

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotNull($responseData, 'Response body is not valid JSON: ' . $body);
        $this->assertArrayHasKey('data', $responseData, 'Response missing \'data\' key: ' . $body);
        $this->assertArrayHasKey('access_token', $responseData['data']);
        $this->assertArrayHasKey('refresh_token', $responseData['data']);
        $this->assertArrayHasKey('token_type', $responseData['data']);
        $this->assertArrayHasKey('expires_in', $responseData['data']);
        $this->assertEquals('Bearer', $responseData['data']['token_type']);
    }

    public function testRefreshWithInvalidTokenReturnsUnauthorized(): void
    {
        // Arrange
        $payload = [
            'refresh_token' => 'invalid-token',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/refresh', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}