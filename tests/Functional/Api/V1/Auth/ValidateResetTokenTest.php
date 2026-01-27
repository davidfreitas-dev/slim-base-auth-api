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
use App\Domain\Repository\PasswordResetRepositoryInterface;
use Tests\Functional\FunctionalTestCase;
use Fig\Http\Message\StatusCodeInterface;

class ValidateResetTokenTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private PasswordResetRepositoryInterface $passwordResetRepository;
    private RoleRepositoryInterface $roleRepository;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->passwordResetRepository = $this->app->getContainer()->get(PasswordResetRepositoryInterface::class);
        $this->roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
    }

    public function testValidateResetTokenWithValidTokenReturnsOk(): void
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

        // This will trigger the creation of a reset token
        $this->sendRequest('POST', '/api/v1/auth/forgot-password', ['email' => 'test@example.com']);
        
        $token = $this->getLatestPasswordResetTokenForUser($user->getId());

        $payload = [
            'email' => 'test@example.com',
            'token' => $token,
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/validate-reset-token', $payload);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        
        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $responseData = json_decode($body, true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Code is valid', $responseData['message']);
    }

    public function testValidateResetTokenWithInvalidTokenReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/validate-reset-token', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Get the latest password reset token for a user directly from database.
     */
    private function getLatestPasswordResetTokenForUser(int $userId): string
    {
        $container = $this->app->getContainer();
        $pdo = $container->get(\PDO::class);
        
        $stmt = $pdo->prepare(
            'SELECT code FROM password_resets 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT 1'
        );
        
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->fail("No password reset token found for user ID: {$userId}");
        }
        
        return $result['code'];
    }
}
