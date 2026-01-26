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

class ChangePasswordTest extends FunctionalTestCase
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

    public function testChangePasswordWithValidDataReturnsOk(): void
    {
        // Arrange - Try with all 3 fields
        $payload = [
            'current_password' => 'password123',
            'new_password' => 'newPassword456',
            'new_password_confirm' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Debug output
        if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
            echo "\n=== CHANGE PASSWORD DEBUG (3 fields) ===";
            echo "\nStatus Code: " . $response->getStatusCode();
            echo "\nResponse Body: " . $body;
            echo "\nPayload: " . json_encode($payload, JSON_PRETTY_PRINT);
            echo "\n========================================\n";
        }

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('message', $responseData);

        // Verify the password was actually changed
        $updatedUser = $this->userRepository->findById($this->user->getId());
        $this->assertTrue(
            password_verify('newPassword456', $updatedUser->getPassword()),
            'Password should be changed to the new password'
        );
        $this->assertFalse(
            password_verify('password123', $updatedUser->getPassword()),
            'Old password should no longer work'
        );
    }

    public function testChangePasswordWithWrongOldPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'wrong-password',
            'new_password' => 'newPassword456',
            'new_password_confirm' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
    }

    public function testChangePasswordWithMismatchingNewPasswordsReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'password123',
            'new_password' => 'newPassword456',
            'new_password_confirm' => 'differentPassword',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
    }

    public function testChangePasswordWithMissingCurrentPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'new_password' => 'newPassword456',
            'new_password_confirm' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
    }

    public function testChangePasswordWithMissingNewPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'password123',
            'new_password_confirm' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
    }

    public function testChangePasswordWithMissingConfirmPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'password123',
            'new_password' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
        $this->assertContains('A confirmação da nova senha é obrigatória.', $responseData['data']);
    }

    public function testChangePasswordWithWeakNewPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'password123',
            'new_password' => '123',
            'new_password_confirm' => '123',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload,
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );

        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $responseData['status']);
    }

    public function testChangePasswordWithoutAuthenticationReturnsUnauthorized(): void
    {
        // Arrange
        $payload = [
            'current_password' => 'password123',
            'new_password' => 'newPassword456',
            'new_password_confirm' => 'newPassword456',
        ];

        // Act
        $response = $this->sendRequest(
            'PATCH',
            '/api/v1/profile/change-password',
            $payload
        );

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}