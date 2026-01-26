<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Role;

interface RoleRepositoryInterface
{
    /**
     * Finds a role by its ID.
     *
     * @param int $id The ID of the role.
     *
     * @return Role|null The found role entity or null if not found.
     */
    public function findById(int $id): ?Role;

    /**
     * Finds a role by its name.
     *
     * @param string $name The name of the role (e.g., 'admin', 'user').
     *
     * @return Role|null The found role entity or null if not found.
     */
    public function findByName(string $name): ?Role;
}
