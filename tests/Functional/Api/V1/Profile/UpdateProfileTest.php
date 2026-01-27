<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Profile;

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

class UpdateProfileTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private User $user;
    private \Faker\Generator $faker;
    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);
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

        $role = $this->roleRepository->findByName('user');
        if (!$role instanceof Role) {
            throw new NotFoundException("Role 'user' not found in the database. Please ensure roles are seeded for testing.");
        }

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

    public function testUpdateProfileWithValidDataReturnsOk(): void
    {
        // Arrange
        $payload = [
            'name' => 'Updated Name',
            'email' => 'updated.email@example.com',
        ];

        // Act
        $response = $this->sendRequest(
            'PUT',
            '/api/v1/profile',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Profile updated successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('name', $responseData['data']);
        $this->assertArrayHasKey('email', $responseData['data']);
        $this->assertArrayHasKey('phone', $responseData['data']);
        $this->assertArrayHasKey('cpfcnpj', $responseData['data']);
        $this->assertArrayHasKey('avatar_url', $responseData['data']);

        $this->assertEquals('Updated Name', $responseData['data']['name']);
        $this->assertEquals('updated.email@example.com', $responseData['data']['email']);

        // Verify in database
        $updatedUser = $this->userRepository->findById($this->user->getId());
        $this->assertNotNull($updatedUser);
        $this->assertEquals('Updated Name', $updatedUser->getPerson()->getName());
        $this->assertEquals('updated.email@example.com', $updatedUser->getPerson()->getEmail());
    }

    public function testUpdateProfileWithInvalidDataReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'email' => 'invalid-email',
        ];

        // Act
        $response = $this->sendRequest(
            'PUT',
            '/api/v1/profile',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUpdateProfileWithConflictingEmailReturnsConflict(): void
    {
        // Arrange: Create another user
        $anotherPerson = $this->personRepository->create(new Person(
            name: 'anotheruser',
            email: 'another@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        ));
        $anotherRole = $this->roleRepository->findByName('user');
        if (!$anotherRole) {
            throw new NotFoundException("Role 'user' not found in the database. Please ensure roles are seeded for testing.");
        }
        $anotherUser = new User(
            person: $anotherPerson,
            password: password_hash('password', PASSWORD_DEFAULT),
            role: $anotherRole,
            isActive: true,
            isVerified: true
        );
        $this->userRepository->create($anotherUser);

        $payload = [
            'email' => 'another@example.com',
        ];

        // Act
        $response = $this->sendRequest(
            'PUT',
            '/api/v1/profile',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        // Assert - API can return either 400 or 409 for conflicts
        $this->assertContains(
            $response->getStatusCode(),
            [StatusCodeInterface::STATUS_BAD_REQUEST, StatusCodeInterface::STATUS_CONFLICT]
        );
    }
}
