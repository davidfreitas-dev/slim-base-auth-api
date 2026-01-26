<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\PasswordReset;
use App\Domain\ValueObject\Code;

interface PasswordResetRepositoryInterface
{
    /**
     * Saves a password reset code record.
     *
     * @param PasswordReset $passwordReset The password reset entity to save.
     */
    public function save(PasswordReset $passwordReset): void;

    /**
     * Finds a password reset record by its code.
     *
     * @param Code $code The code to search for.
     *
     * @return PasswordReset|null The found password reset entity or null if not found.
     */
    public function findByCode(Code $code): ?PasswordReset;

    /**
     * Marks a password reset code as used.
     *
     * @param Code $code The code to mark as used.
     *
     * @return bool True on success, false otherwise.
     */
    public function markAsUsed(Code $code): bool;

    /**
     * Deletes all expired password reset codes.
     *
     * @return int The number of deleted records.
     */
    public function deleteExpired(): int;
}
