<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj; 
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use Faker\Factory;
use Tests\Integration\DatabaseTestCase;

class PersonRepositoryTest extends DatabaseTestCase
{
    private PersonRepository $personRepository;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->personRepository = new PersonRepository(self::$pdo);
        $this->faker = Factory::create('pt_BR');
    }

    private function createTestPerson(): Person
    {
        $person = new Person(
            name: $this->faker->name,
            email: $this->faker->unique()->email,
            phone: $this->faker->phoneNumber,
            cpfcnpj: CpfCnpj::fromString($this->faker->unique()->cpf), // Generate a valid CPF
        );
        
        return $this->personRepository->create($person);
    }

    public function testCreateAndFindById(): void
    {
        $createdPerson = $this->createTestPerson();

        $this->assertNotNull($createdPerson->getId(), 'Person ID should not be null after creation');

        $foundPerson = $this->personRepository->findById($createdPerson->getId());

        $this->assertNotNull($foundPerson, 'Should find a person by ID');
        $this->assertEquals($createdPerson->getId(), $foundPerson->getId());
        $this->assertEquals($createdPerson->getEmail(), $foundPerson->getEmail());
        $this->assertEquals($createdPerson->getName(), $foundPerson->getName());
    }

    public function testFindByEmail(): void
    {
        $createdPerson = $this->createTestPerson();
        $email = $createdPerson->getEmail();

        $foundPerson = $this->personRepository->findByEmail($email);

        $this->assertNotNull($foundPerson, 'Should find a person by email');
        $this->assertEquals($email, $foundPerson->getEmail());
    }

    public function testFindByCpfCnpj(): void
    {
        $createdPerson = $this->createTestPerson();
        $cpfCnpj = $createdPerson->getCpfCnpj();

        $foundPerson = $this->personRepository->findByCpfCnpj($cpfCnpj);

        $this->assertNotNull($foundPerson, 'Should find a person by CPF/CNPJ');
        $this->assertEquals($cpfCnpj, $foundPerson->getCpfCnpj());
    }

    public function testUpdate(): void
    {
        $createdPerson = $this->createTestPerson();

        $newName = 'Updated Name ' . $this->faker->name;
        $newEmail = $this->faker->unique()->email;
        
        $createdPerson->setName($newName);
        $createdPerson->setEmail($newEmail);

        $this->personRepository->update($createdPerson);

        $updatedPerson = $this->personRepository->findById($createdPerson->getId());
        
        $this->assertNotNull($updatedPerson, 'Updated person should be found');
        $this->assertEquals($newName, $updatedPerson->getName(), 'Person name should be updated');
        $this->assertEquals($newEmail, $updatedPerson->getEmail(), 'Person email should be updated');
    }

    public function testDelete(): void
    {
        $createdPerson = $this->createTestPerson();
        $personId = $createdPerson->getId();

        $deleted = $this->personRepository->delete($personId);
        $this->assertTrue($deleted, 'Delete method should return true on success');

        $foundPerson = $this->personRepository->findById($personId);
        $this->assertNull($foundPerson, 'Person should not be found after deletion');
    }

    public function testFindByIdNotFound(): void
    {
        $foundPerson = $this->personRepository->findById(999999);
        $this->assertNull($foundPerson, 'Should not find a person with a non-existent ID');
    }
}
