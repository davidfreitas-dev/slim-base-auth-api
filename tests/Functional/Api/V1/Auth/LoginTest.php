<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj; 
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;
use DateTimeImmutable;
use Faker\Factory;

class LoginTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;

    private PersonRepositoryInterface $personRepository;

    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
    }

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        // Arrange
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Criar e salvar Person primeiro (para gerar o ID)
        $person = new Person(
            name: 'testuser',
            email: 'test@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        // Criar Role
        $role = new Role(
            1,
            'user',
            'User role',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        // Criar User com Person que já tem ID
        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: false
        );

        $user->activate();

        $this->userRepository->create($user);

        $payload = [
            'email' => 'test@example.com',
            'password' => $password,
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/login', $payload);
        $response->getBody()->rewind(); // Resetar o ponteiro do stream
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Debug em caso de falha
        if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            $this->fail(sprintf('Login failed with status %s: %s', $response->getStatusCode(), $body));
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

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        // Arrange
        $payload = [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/login', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}