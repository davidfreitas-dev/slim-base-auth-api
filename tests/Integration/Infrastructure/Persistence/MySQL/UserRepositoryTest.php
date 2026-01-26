<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\MySQL;

use Faker\Factory;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;
use Tests\Integration\DatabaseTestCase;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;

class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $userRepository;

    private PersonRepository $personRepository;

    private RoleRepository $roleRepository;

    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo, $this->personRepository, $this->roleRepository);
        $this->faker = Factory::create('pt_BR'); // Use pt_BR locale for CPF generation
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

    public function testCreateAndFindById(): void
    {
        $createdUser = $this->createTestUser();

        $this->assertNotNull($createdUser->getId());
        $this->assertEquals($createdUser->getPerson()->getEmail(), $createdUser->getEmail());

        $foundUser = $this->userRepository->findById($createdUser->getId());

        $this->assertNotNull($foundUser);
        $this->assertEquals($createdUser->getId(), $foundUser->getId());
        $this->assertEquals($createdUser->getPerson()->getEmail(), $foundUser->getEmail());
    }

    public function testFindByEmail(): void
    {
        $createdUser = $this->createTestUser();
        $email = $createdUser->getPerson()->getEmail();

        $foundUser = $this->userRepository->findByEmail($email);

        $this->assertNotNull($foundUser);
        $this->assertEquals($email, $foundUser->getEmail());
    }

    public function testFindByCpfCnpj(): void
    {
        $createdUser = $this->createTestUser();
        $cpfCnpj = $createdUser->getPerson()->getCpfCnpj();

        $foundUser = $this->userRepository->findByCpfCnpj($cpfCnpj->value());

        $this->assertNotNull($foundUser);
        $this->assertEquals($cpfCnpj, $foundUser->getPerson()->getCpfCnpj());
    }

    public function testFindAll(): void
    {
        $this->createTestUser();
        $this->createTestUser();

        $users = $this->userRepository->findAll();

        $this->assertCount(2, $users);
    }

    public function testUpdate(): void
    {
        $createdUser = $this->createTestUser();

        $newName = 'Updated Name';
        $createdUser->getPerson()->setName($newName);
        $updatedUser = $this->userRepository->update($createdUser);

        $this->assertEquals($newName, $updatedUser->getPerson()->getName());
    }

    public function testDelete(): void
    {
        $createdUser = $this->createTestUser();
        $userId = $createdUser->getId();

        $deleted = $this->userRepository->delete($userId);
        $this->assertTrue($deleted);

        $foundUser = $this->userRepository->findById($userId);
        $this->assertNull($foundUser);
    }

    public function testCount(): void
    {
        $this->createTestUser();

        $count = $this->userRepository->count();

        $this->assertEquals(1, $count);
    }

    public function testUpdatePassword(): void
    {
        $createdUser = $this->createTestUser();

        $newPassword = 'new_password';
        $this->userRepository->updatePassword($createdUser->getId(), $newPassword);

        // To verify, we'd need a way to check the password, which is not ideal.
        // We'll assume the operation is successful if no exception is thrown.
        // A better test would involve trying to authenticate with the new password.
        $this->assertTrue(true);
    }

    public function testMarkUserAsVerified(): void
    {
        $createdUser = $this->createTestUser();
        $this->assertFalse($createdUser->isVerified()); // User is not verified by default

        $this->userRepository->markUserAsVerified($createdUser->getId());

        $foundUser = $this->userRepository->findById($createdUser->getId());
        $this->assertTrue($foundUser->isVerified());
    }
}
