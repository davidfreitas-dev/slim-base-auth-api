<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\MySQL;

use Faker\Factory;
use Ramsey\Uuid\Uuid;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use App\Domain\Entity\UserVerification;
use Tests\Integration\DatabaseTestCase;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use App\Infrastructure\Persistence\MySQL\UserVerificationRepository;

class UserVerificationRepositoryTest extends DatabaseTestCase
{
    private UserVerificationRepository $userVerificationRepository;

    private UserRepository $userRepository;

    private \Faker\Generator $faker;

    private PersonRepository $personRepository;

    private RoleRepository $roleRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userVerificationRepository = new UserVerificationRepository(self::$pdo);
        $this->faker = Factory::create('pt_BR');

        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo, $this->personRepository, $this->roleRepository);
    }

    private function createTestUser(int $roleId = 1): User
    {
        $person = new Person(
            name: $this->faker->name,
            email: $this->faker->email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $this->personRepository->create($person);

        $role = $this->roleRepository->findById($roleId);
        if (!$role instanceof Role) {
            throw new NotFoundException(sprintf("Role with ID %d not found. Make sure it is seeded.", $roleId));
        }

        $user = new User(
            person: $person,
            password: 'password',
            role: $role
        );
        return $this->userRepository->create($user);
    }

    public function testCreateAndFindByToken(): void
    {
        $user = $this->createTestUser();
        $token = Uuid::uuid4()->toString();
        $expiresAt = new \DateTimeImmutable('+1 day');

        $verification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: $expiresAt
        );

        $createdVerification = $this->userVerificationRepository->create($verification);

        $foundVerification = $this->userVerificationRepository->findByToken($token);

        $this->assertNotNull($foundVerification);
        $this->assertEquals($user->getId(), $foundVerification->getUserId());
        $this->assertEquals($token, $foundVerification->getToken());
    }

    public function testMarkAsUsed(): void
    {
        $user = $this->createTestUser();
        $token = Uuid::uuid4()->toString();
        $verification = new UserVerification(
            userId: $user->getId(),
            token: $token,
            expiresAt: new \DateTimeImmutable('+1 day')
        );
        $this->userVerificationRepository->create($verification);

        $this->userVerificationRepository->markAsUsed($token);

        $foundVerification = $this->userVerificationRepository->findByToken($token);
        $this->assertNotNull($foundVerification->getUsedAt());
    }

    public function testDeleteOldVerifications(): void
    {
        $user = $this->createTestUser();
        $token1 = Uuid::uuid4()->toString();
        $verification1 = new UserVerification( // old one
            userId: $user->getId(),
            token: $token1,
            expiresAt: new \DateTimeImmutable('-1 day')
        );
        $this->userVerificationRepository->create($verification1);

        $token2 = Uuid::uuid4()->toString();
        $verification2 = new UserVerification( // new one
            userId: $user->getId(),
            token: $token2,
            expiresAt: new \DateTimeImmutable('+1 day')
        );
        $this->userVerificationRepository->create($verification2);

        $this->userVerificationRepository->deleteOldVerifications($user->getId());

        $this->assertNull($this->userVerificationRepository->findByToken($token1));
        $this->assertNotNull($this->userVerificationRepository->findByToken($token2));
    }
}
