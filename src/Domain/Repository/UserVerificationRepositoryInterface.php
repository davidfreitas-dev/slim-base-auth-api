<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\UserVerification;

interface UserVerificationRepositoryInterface
{
    /**
     * Creates a new user verification record.
     *
     * @param UserVerification $verification The verification entity to create.
     *
     * @return UserVerification The created verification entity, possibly with an updated ID.
     */
    public function create(UserVerification $verification): UserVerification;

    /**
     * Finds a user verification record by its token.
     *
     * @param string $token The verification token.
     *
     * @return UserVerification|null The found verification entity or null if not found.
     */
    public function findByToken(string $token): ?UserVerification;

    /**
     * Marks a verification token as used.
     *
     * @param string $token The token to mark as used.
     */
    public function markAsUsed(string $token): void;

    /**
     * Deletes any old, unused verification records for a specific user.
     *
     * @param int $userId The ID of the user whose old verifications should be deleted.
     */
    public function deleteOldVerifications(int $userId): void;
}
