<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;

class RegisterTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private UserVerificationRepositoryInterface $userVerificationRepository;
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->userVerificationRepository = $this->app->getContainer()->get(UserVerificationRepositoryInterface::class);
        $this->jwtService = $this->app->getContainer()->get(JwtService::class);
    }

    public function testShouldSuccessfullyCompleteUserRegistrationFlow(): void
    {
        // Step 1: User Registration
        $registerPayload = [
            'name' => 'Test User',
            'email' => 'test.user@example.com',
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '34028316000103', // CNPJ válido
            'phone' => '11999999999'
        ];

        $registerResponse = $this->sendRequest('POST', '/api/v1/auth/register', $registerPayload);
        $registerResponse->getBody()->rewind();
        $registerBody = $registerResponse->getBody()->getContents();
        $registerData = json_decode($registerBody, true);

        $this->assertEquals(StatusCodeInterface::STATUS_CREATED, $registerResponse->getStatusCode());
        $this->assertEquals('success', $registerData['status']);
        $this->assertArrayHasKey('data', $registerData);
        $this->assertArrayHasKey('access_token', $registerData['data']);

        // Step 2: Verify initial token is restricted
        $initialAccessToken = $registerData['data']['access_token'];
        $decodedInitialToken = $this->jwtService->validateToken($initialAccessToken);
        $this->assertFalse($decodedInitialToken->is_verified, 'Initial token should be restricted');

        // Step 3: Retrieve user and verification token
        /** @var User|null $user */
        $user = $this->userRepository->findByEmail($registerPayload['email']);
        $this->assertNotNull($user, 'User should exist after registration');
        $this->assertFalse($user->isVerified(), 'User should not be verified initially');

        // Get verification token directly from database
        $verificationToken = $this->getLatestVerificationTokenForUser($user->getId());
        $this->assertNotEmpty($verificationToken, 'Verification token should exist');

        // Step 4: Confirm email (ROTA CORRIGIDA - usando query parameter)
        $verifyResponse = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $verificationToken, []);
        $verifyResponse->getBody()->rewind();
        $verifyBody = $verifyResponse->getBody()->getContents();
        $verifyData = json_decode($verifyBody, true);

        $this->assertEquals(StatusCodeInterface::STATUS_OK, $verifyResponse->getStatusCode());
        $this->assertEquals('success', $verifyData['status']);
        $this->assertArrayHasKey('data', $verifyData);
        $this->assertArrayHasKey('access_token', $verifyData['data']);

        // Step 5: Validate final token has full access
        $finalAccessToken = $verifyData['data']['access_token'];
        $decodedFinalToken = $this->jwtService->validateToken($finalAccessToken);
        $this->assertTrue($decodedFinalToken->is_verified, 'Final token should not be restricted');

        // Step 6: Verify user status in database
        $updatedUser = $this->userRepository->findById($user->getId());
        $this->assertNotNull($updatedUser, 'Updated user should exist');
        $this->assertTrue($updatedUser->isVerified(), 'User should be verified after email confirmation');
    }

    public function testRegisterWithExistingEmailReturnsConflict(): void
    {
        // Use email único baseado no timestamp para evitar conflitos com outros testes
        $uniqueEmail = 'duplicate.' . time() . rand(1000, 9999) . '@example.com';
        
        // Arrange: Create first user
        $payload = [
            'name' => 'First User',
            'email' => $uniqueEmail,
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '06990590000123', // CNPJ válido diferente
            'phone' => '11999999999'
        ];

        $firstResponse = $this->sendRequest('POST', '/api/v1/auth/register', $payload);
        $this->assertEquals(StatusCodeInterface::STATUS_CREATED, $firstResponse->getStatusCode());

        // Act: Try to register with same email
        $duplicatePayload = [
            'name' => 'Second User',
            'email' => $uniqueEmail, // Mesmo email
            'password' => 'An0th3rP@ss!',
            'cpfcnpj' => '34028316000103', // CNPJ válido diferente  
            'phone' => '11987654321' // Formato válido: (11) 98765-4321
        ];

        $response = $this->sendRequest('POST', '/api/v1/auth/register', $duplicatePayload);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_CONFLICT, $response->getStatusCode());
        $this->assertEquals('fail', $data['status']);
        $this->assertNotEmpty($data['message']);
    }

    public function testRegisterWithExistingCpfCnpjReturnsConflict(): void
    {
        // Use email único para evitar conflitos
        $uniqueEmail1 = 'user1.' . time() . rand(1000, 9999) . '@example.com';
        $uniqueEmail2 = 'user2.' . time() . rand(1000, 9999) . '@example.com';
        
        // Arrange: Create first user
        $payload = [
            'name' => 'First User',
            'email' => $uniqueEmail1,
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '11222333000181', // CNPJ válido
            'phone' => '11999999999'
        ];

        $firstResponse = $this->sendRequest('POST', '/api/v1/auth/register', $payload);
        $this->assertEquals(StatusCodeInterface::STATUS_CREATED, $firstResponse->getStatusCode());

        // Act: Try to register with same CPF/CNPJ
        $duplicatePayload = [
            'name' => 'Second User',
            'email' => $uniqueEmail2, // Email diferente
            'password' => 'An0th3rP@ss!',
            'cpfcnpj' => '11222333000181', // Mesmo CNPJ válido
            'phone' => '11987654321' // Formato válido
        ];

        $response = $this->sendRequest('POST', '/api/v1/auth/register', $duplicatePayload);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_CONFLICT, $response->getStatusCode());
        $this->assertEquals('fail', $data['status']);
    }

    public function testRegisterWithInvalidEmailReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '34028316000103', // CNPJ válido
            'phone' => '11999999999'
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/register', $payload);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('fail', $data['status']);
    }

    public function testRegisterWithWeakPasswordReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'cpfcnpj' => '34028316000103', // CNPJ válido
            'phone' => '11999999999'
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/register', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithInvalidCpfCnpjReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '123',
            'phone' => '11999999999'
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/register', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithMissingFieldsReturnsBadRequest(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com'
            // Missing password and cpfcnpj
        ];

        // Act
        $response = $this->sendRequest('POST', '/api/v1/auth/register', $payload);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerifyEmailWithInvalidTokenReturnsNotFound(): void
    {
        // Arrange
        $invalidToken = 'invalid-token-12345';

        // Act (ROTA CORRIGIDA - usando query parameter)
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $invalidToken, []);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testVerifyEmailWithUsedTokenReturnsBadRequest(): void
    {
        // Use email único
        $uniqueEmail = 'verify.test.' . time() . rand(1000, 9999) . '@example.com';
        
        // Arrange: Register and verify user
        $payload = [
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'Str0ngP@ss!',
            'cpfcnpj' => '60701190000104', // CNPJ válido
            'phone' => '11999999999'
        ];

        $registerResponse = $this->sendRequest('POST', '/api/v1/auth/register', $payload);
        $this->assertEquals(StatusCodeInterface::STATUS_CREATED, $registerResponse->getStatusCode());
        
        $user = $this->userRepository->findByEmail($payload['email']);
        
        // Verifica se o usuário foi criado
        if (!$user) {
            $this->fail("User was not created successfully");
        }
        
        $token = $this->getLatestVerificationTokenForUser($user->getId());

        // First verification (should succeed) - ROTA CORRIGIDA
        $firstResponse = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token, []);
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $firstResponse->getStatusCode());

        // Act: Try to use the same token again - ROTA CORRIGIDA
        $secondResponse = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token, []);

        // Assert
        $this->assertContains(
            $secondResponse->getStatusCode(),
            [StatusCodeInterface::STATUS_BAD_REQUEST, StatusCodeInterface::STATUS_NOT_FOUND]
        );
    }

    /**
     * Get the latest verification token for a user directly from database.
     */
    private function getLatestVerificationTokenForUser(int $userId): string
    {
        $container = $this->app->getContainer();
        $pdo = $container->get(\PDO::class);
        
        $stmt = $pdo->prepare(
            'SELECT token FROM user_verifications 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT 1'
        );
        
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->fail("No verification token found for user ID: {$userId}");
        }
        
        return $result['token'];
    }
}