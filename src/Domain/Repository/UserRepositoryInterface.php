<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    /**
     * Creates a new user record.
     *
     * @param User $user The user entity to create.
     *
     * @return User The created user entity.
     */
    public function create(User $user): User;

    /**
     * Finds a user by their ID.
     *
     * @param int $id The ID of the user.
     *
     * @return User|null The found user entity or null if not found.
     */
    public function findById(int $id): ?User;

    /**
     * Finds a user by their email address.
     *
     * @param string $email The email address to search for.
     *
     * @return User|null The found user entity or null if not found.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Finds a user by their CPF/CNPJ.
     *
     * @param string $cpfcnpj The CPF/CNPJ to search for.
     *
     * @return User|null The found user entity or null if not found.
     */
    public function findByCpfCnpj(string $cpfcnpj): ?User;

    /**
     * Retrieves a paginated list of all users.
     *
     * @param int $limit  The maximum number of users to return.
     * @param int $offset The starting point for the list of users.
     *
     * @return User[] An array of user entities.
     */
    public function findAll(int $limit = 20, int $offset = 0): array;

    /**
     * Updates an existing user record.
     *
     * @param User $user The user entity with updated data.
     *
     * @return User The updated user entity.
     */
    public function update(User $user): User;

    /**
     * Updates only the password for a specific user.
     *
     * @param int    $userId      The ID of the user to update.
     * @param string $newPassword The new, hashed password.
     */
    public function updatePassword(int $userId, string $newPassword): void;

    /**
     * Marks a user's account as verified.
     *
     * @param int $userId The ID of the user to mark as verified.
     */
    public function markUserAsVerified(int $userId): void;

    /**
     * Deletes a user record by their ID.
     *
     * @param int $id The ID of the user to delete.
     *
     * @return bool True on success, false otherwise.
     */
    public function delete(int $id): bool;

    /**
     * Counts the total number of users.
     *
     * @return int The total number of users.
     */
    public function count(): int;
}
