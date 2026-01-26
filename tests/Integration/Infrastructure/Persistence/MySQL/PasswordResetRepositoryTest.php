<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\PasswordReset;
use App\Domain\Entity\User;
use App\Domain\Entity\Role;
use App\Domain\ValueObject\Code;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Persistence\MySQL\PasswordResetRepository;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use Faker\Factory;
use Tests\Integration\DatabaseTestCase;

class PasswordResetRepositoryTest extends DatabaseTestCase
{
    private PasswordResetRepository $passwordResetRepository;

    private \Faker\Generator $faker;

    private UserRepository $userRepository;

    private PersonRepository $personRepository;

    private RoleRepository $roleRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordResetRepository = new PasswordResetRepository(self::$pdo);
        $this->faker = Factory::create('pt_BR'); // Use pt_BR locale for CPF generation

        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo, $this->personRepository, $this->roleRepository);
    }

    private function createTestUser(int $roleId = 1): User
    {
        $person = new Person(
            name: $this->faker->name,
            email: $this->faker->email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf()) // Generate a valid CPF
        );
        $this->personRepository->create($person);

        $role = $this->roleRepository->findById($roleId);
        if (!$role instanceof Role) {
            throw new NotFoundException(sprintf("Role with ID %d not found. Make sure it is seeded.", $roleId));
        }

        $user = new User(
            person: $person,
            password: 'password123',
            role: $role
        );
        return $this->userRepository->create($user);
    }

    public function testSaveAndFindByCode(): void
    {
        $user = $this->createTestUser();
        $code = Code::from($this->faker->numerify('######'));
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $ipAddress = $this->faker->ipv4;

        $passwordReset = new PasswordReset(
            id: null,
            userId: $user->getId(),
            code: $code,
            expiresAt: $expiresAt,
            usedAt: null,
            ipAddress: $ipAddress
        );

        $this->passwordResetRepository->save($passwordReset);

        $foundPasswordReset = $this->passwordResetRepository->findByCode($code);

        $this->assertNotNull($foundPasswordReset);
        $this->assertEquals($user->getId(), $foundPasswordReset->getUserId());
        $this->assertEquals($code->value, $foundPasswordReset->getCode()->value);
        $this->assertEquals($expiresAt->format('Y-m-d H:i:s'), $foundPasswordReset->getExpiresAt()->format('Y-m-d H:i:s'));
        $this->assertEquals($ipAddress, $foundPasswordReset->getIpAddress());
    }

    public function testMarkAsUsed(): void
    {
        $user = $this->createTestUser();
        $code = Code::from($this->faker->numerify('######'));
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $ipAddress = $this->faker->ipv4;
        $passwordReset = new PasswordReset(
            id: null,
            userId: $user->getId(),
            code: $code,
            expiresAt: $expiresAt,
            usedAt: null,
            ipAddress: $ipAddress
        );
        $this->passwordResetRepository->save($passwordReset);

        $marked = $this->passwordResetRepository->markAsUsed($code);
        $this->assertTrue($marked);

        $foundPasswordReset = $this->passwordResetRepository->findByCode($code);
        $this->assertNull($foundPasswordReset); // Should not find it after marking as used
    }

    public function testDeleteExpired(): void
    {
        $user1 = $this->createTestUser();
        $code1 = Code::from($this->faker->numerify('######')); // Expired
        $expiresAt1 = new \DateTimeImmutable('-1 hour');
        $ipAddress1 = $this->faker->ipv4;
        $passwordReset1 = new PasswordReset(
            id: null,
            userId: $user1->getId(),
            code: $code1,
            expiresAt: $expiresAt1,
            usedAt: null,
            ipAddress: $ipAddress1
        );
        $this->passwordResetRepository->save($passwordReset1);

        $user2 = $this->createTestUser();
        $code2 = Code::from($this->faker->numerify('######')); // Not expired
        $expiresAt2 = new \DateTimeImmutable('+1 hour');
        $ipAddress2 = $this->faker->ipv4;
        $passwordReset2 = new PasswordReset(
            id: null,
            userId: $user2->getId(),
            code: $code2,
            expiresAt: $expiresAt2,
            usedAt: null,
            ipAddress: $ipAddress2
        );
        $this->passwordResetRepository->save($passwordReset2);

        $deletedCount = $this->passwordResetRepository->deleteExpired();
        $this->assertEquals(1, $deletedCount);

        $this->assertNull($this->passwordResetRepository->findByCode($code1));
        $this->assertNotNull($this->passwordResetRepository->findByCode($code2));
    }
}
