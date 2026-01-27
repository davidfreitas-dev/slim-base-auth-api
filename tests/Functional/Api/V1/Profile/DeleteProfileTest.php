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

class DeleteProfileTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private User $user;
    private string $accessToken;
    private \Faker\Generator $faker;

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

    public function testDeleteProfileWithAuthenticatedUserReturnsNoContent(): void
    {
        // Act
        $response = $this->sendRequest(
            'DELETE',
            '/api/v1/profile',
            [],
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        // Assert - API can return either 200 or 204
        $this->assertContains(
            $response->getStatusCode(),
            [StatusCodeInterface::STATUS_OK, StatusCodeInterface::STATUS_NO_CONTENT]
        );

        // Verify user is deleted from database (not just deactivated)
        $deletedUser = $this->userRepository->findById($this->user->getId());
        $this->assertNull($deletedUser, "User should be deleted from database");
    }

    public function testDeleteProfileWithoutAuthenticationReturnsUnauthorized(): void
    {
        // Act
        $response = $this->sendRequest('DELETE', '/api/v1/profile', []);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testDeleteProfileOfInactiveUserReturnsNoContent(): void
    {
        // Arrange: Deactivate user first
        $this->user->deactivate();
        $this->userRepository->update($this->user);

        // Act
        $response = $this->sendRequest(
            'DELETE',
            '/api/v1/profile',
            [],
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        // Assert - API can return either 200 or 204
        $this->assertContains(
            $response->getStatusCode(),
            [StatusCodeInterface::STATUS_OK, StatusCodeInterface::STATUS_NO_CONTENT]
        );

        // Verify user is deleted from database
        $deletedUser = $this->userRepository->findById($this->user->getId());
        $this->assertNull($deletedUser, "User should be deleted from database");
    }
}
