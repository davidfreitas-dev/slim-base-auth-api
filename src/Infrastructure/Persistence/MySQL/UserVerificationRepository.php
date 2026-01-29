<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\UserVerification;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use DateTimeImmutable;
use PDO;

class UserVerificationRepository implements UserVerificationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(UserVerification $verification): UserVerification
    {
        $sql = 'INSERT INTO user_verifications (user_id, token, expires_at, created_at, updated_at)
                VALUES (:user_id, :token, :expires_at, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $verification->getUserId(),
            'token' => $verification->getToken(),
            'expires_at' => $verification->getExpiresAt()->format('Y-m-d H:i:s'),
        ]);

        return $verification;
    }

    public function findByToken(string $token): ?UserVerification
    {
        $sql = 'SELECT * FROM user_verifications WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new UserVerification(
            userId: (int)$data['user_id'],
            token: $data['token'],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            usedAt: $data['used_at'] ? new DateTimeImmutable($data['used_at']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );
    }

    public function markAsUsed(string $token): void
    {
        $sql = 'UPDATE user_verifications SET used_at = NOW(), updated_at = NOW() WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token' => $token]);
    }

    public function deleteOldVerifications(int $userId): void
    {
        $sql = 'DELETE FROM user_verifications WHERE user_id = :user_id AND (expires_at < NOW() OR used_at IS NOT NULL)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
    }
}
