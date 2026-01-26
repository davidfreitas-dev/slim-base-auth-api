<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Profile;

use DateTimeImmutable;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use Tests\Functional\FunctionalTestCase;
use Fig\Http\Message\StatusCodeInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use Faker\Factory;

class GetProfileTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private User $user;
    private string $accessToken;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
        $this->setUpUser();
    }

    private function setUpUser(): void
    {
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

        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );
        $this->userRepository->create($this->user);

        $response = $this->sendRequest('POST', '/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Login failed with status %s: %s', $response->getStatusCode(), $body)
            );
        }

        if (!isset($responseData['data']['access_token'])) {
            throw new \RuntimeException(
                'Access token not found in response: ' . $body
            );
        }

        $this->accessToken = $responseData['data']['access_token'];
    }
    
    public function testGetProfileWithAuthenticatedUserReturnsProfileData(): void
    {
        // Act
        $response = $this->sendRequest(
            'GET',
            '/api/v1/profile',
            [],
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($this->user->getPerson()->getEmail(), $responseData['data']['email']);
        $this->assertEquals($this->user->getPerson()->getName(), $responseData['data']['name']);
    }

    public function testGetProfileWithoutAuthenticationReturnsUnauthorized(): void
    {
        // Act
        $response = $this->sendRequest('GET', '/api/v1/profile', []);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}
