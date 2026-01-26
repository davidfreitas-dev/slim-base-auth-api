<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

class PasswordHasher
{
    public function hash(string $password): string
    {
        // Placeholder for actual password hashing logic
        return \password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hashedPassword): bool
    {
        // Placeholder for actual password verification logic
        return \password_verify($password, $hashedPassword);
    }
}
