<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Auth;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\UserVerification;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;
use Faker\Factory;

class VerifyEmailTest extends FunctionalTestCase
{
    private PersonRepositoryInterface $personRepository;

    private UserRepositoryInterface $userRepository;

    private UserVerificationRepositoryInterface $userVerificationRepository;

    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->userVerificationRepository = $this->app->getContainer()->get(UserVerificationRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
    }

    public function testVerifyEmailWithValidTokenReturnsOk(): void
    {
        // Arrange
        $person = new Person(
            name: 'testuser',
            email: 'test@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = new Role(
            1,
            'user',
            'User role',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $user = new User(
            person: $person,
            password: 'hashedpassword',
            role: $role,
            isActive: true,
            isVerified: false
        );
        $user = $this->userRepository->create($user);

        $token = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $userVerification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: new DateTimeImmutable('+1 hour')
        );
        $this->userVerificationRepository->create($userVerification);

        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotNull($responseData, 'Response body is not valid JSON: ' . $body);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Email verified successfully.', $responseData['message']);

        $updatedUser = $this->userRepository->findById($user->getId());
        $this->assertTrue($updatedUser->isVerified());
    }

    public function testVerifyEmailWithInvalidTokenReturnsNotFound(): void
    {
        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=invalidtoken');

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testVerifyEmailWithExpiredTokenReturnsBadRequest(): void
    {
        // Arrange
        $person = new Person(
            name: 'testuser',
            email: 'test2@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = new Role(
            1,
            'user',
            'User role',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $user = new User(
            person: $person,
            password: 'hashedpassword',
            role: $role,
            isActive: true,
            isVerified: false
        );
        $user = $this->userRepository->create($user);

        $token = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $userVerification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: new DateTimeImmutable('-1 hour')
        );
        $this->userVerificationRepository->create($userVerification);

        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerifyEmailForAlreadyVerifiedUserReturnsOk(): void
    {
        // Arrange
        $person = new Person(
            name: 'testuser',
            email: 'verified@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = new Role(
            1,
            'user',
            'User role',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        // User is already verified
        $user = new User(
            person: $person,
            password: 'hashedpassword',
            role: $role,
            isActive: true,
            isVerified: true
        );
        $user = $this->userRepository->create($user);

        $token = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $userVerification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: new DateTimeImmutable('+1 hour')
        );
        $this->userVerificationRepository->create($userVerification);

        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $responseData = json_decode($body, true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotNull($responseData, 'Response body is not valid JSON: ' . $body);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Email already verified.', $responseData['message']);

        $updatedUser = $this->userRepository->findById($user->getId());
        $this->assertTrue($updatedUser->isVerified());
    }

    public function testVerifyEmailWithoutTokenReturnsBadRequest(): void
    {
        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email');

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerifyEmailWithNonExistentUserReturnsNotFound(): void
    {
        // Arrange
        $person = new Person(
            name: 'testuser',
            email: 'ghost@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = new Role(
            1,
            'user',
            'User role',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $user = new User(
            person: $person,
            password: 'hashedpassword',
            role: $role,
            isActive: true,
            isVerified: false
        );
        $user = $this->userRepository->create($user);

        $token = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $userVerification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: new DateTimeImmutable('+1 hour')
        );
        $this->userVerificationRepository->create($userVerification);

        // Now, delete the user to simulate the condition
        $this->userRepository->delete($user->getId());

        // Act
        $response = $this->sendRequest('GET', '/api/v1/auth/verify-email?token=' . $token);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }
}
