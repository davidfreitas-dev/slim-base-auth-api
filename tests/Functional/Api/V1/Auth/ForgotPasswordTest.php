<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj; 
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;
use DateTimeImmutable;
use Faker\Factory;

class ForgotPasswordTest extends FunctionalTestCase
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

    public function testForgotPasswordWithValidEmailReturnsOk(): void
    {
        // Arrange
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $person = new Person(
            name: 'testuser',
            email: 'test@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = new Role(
            id: 1,
            name: 'user',
            description: 'User role',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable()
        );

        $user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );

        $this->userRepository->create($user);

        $payload = [
            'email' => 'test@example.com',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/forgot-password', $payload);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $responseData = json_decode($body, true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('If this email exists, a password reset email has been sent.', $responseData['message']);
    }

    public function testForgotPasswordWithInvalidEmailReturnsOk(): void
    {
        // Arrange
        $payload = [
            'email' => 'wrong@example.com',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/forgot-password', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
