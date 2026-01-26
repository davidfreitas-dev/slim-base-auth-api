<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Person;
use App\Domain\ValueObject\CpfCnpj;

interface PersonRepositoryInterface
{
    /**
     * Creates a new person record.
     *
     * @param Person $person The person entity to create.
     *
     * @return Person The created person entity, possibly with an updated ID.
     */
    public function create(Person $person): Person;

    /**
     * Finds a person by their ID.
     *
     * @param int $id The ID of the person.
     *
     * @return Person|null The found person entity or null if not found.
     */
    public function findById(int $id): ?Person;

    /**
     * Finds a person by their email address.
     *
     * @param string $email The email address to search for.
     *
     * @return Person|null The found person entity or null if not found.
     */
    public function findByEmail(string $email): ?Person;

    /**
     * Finds a person by their CPF/CNPJ.
     *
     * @param string|CpfCnpj $cpfcnpj The CPF/CNPJ to search for.
     *
     * @return Person|null The found person entity or null if not found.
     */
    public function findByCpfCnpj(string|CpfCnpj $cpfcnpj): ?Person;

    /**
     * Updates an existing person record.
     *
     * @param Person $person The person entity with updated data.
     *
     * @return Person The updated person entity.
     */
    public function update(Person $person): Person;

    /**
     * Deletes a person record by their ID.
     *
     * @param int $id The ID of the person to delete.
     *
     * @return bool True on success, false otherwise.
     */
    public function delete(int $id): bool;
}
