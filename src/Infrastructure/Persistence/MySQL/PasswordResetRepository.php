<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Entity\PasswordReset;
use App\Domain\Repository\PasswordResetRepositoryInterface;
use App\Domain\ValueObject\Code;
use DateTimeImmutable;
use PDO;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PasswordReset $passwordReset): void
    {
        $sql = 'INSERT INTO password_resets (user_id, code, expires_at, used_at, ip_address, created_at, updated_at)
                VALUES (:user_id, :code, :expires_at, :used_at, :ip_address, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $passwordReset->getUserId(),
            'code' => $passwordReset->getCode()->value,
            'expires_at' => $passwordReset->getExpiresAt()->format('Y-m-d H:i:s'),
            'used_at' => $passwordReset->getUsedAt()?->format('Y-m-d H:i:s'),
            'ip_address' => $passwordReset->getIpAddress(),
        ]);
    }

    public function findByCode(Code $code): ?PasswordReset
    {
        $sql = 'SELECT * FROM password_resets
                WHERE code = :code
                AND used_at IS NULL
                AND expires_at > NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['code' => $code->value]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new PasswordReset(
            (int)$data['id'],
            (int)$data['user_id'],
            Code::from($data['code']),
            new DateTimeImmutable($data['expires_at']),
            $data['used_at'] ? new DateTimeImmutable($data['used_at']) : null,
            $data['ip_address'],
        );
    }

    public function markAsUsed(Code $code): bool
    {
        $sql = 'UPDATE password_resets SET used_at = NOW(), updated_at = NOW() WHERE code = :code';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['code' => $code->value]);

        return $stmt->rowCount() > 0;
    }

    public function deleteExpired(): int
    {
        $sql = 'DELETE FROM password_resets
                WHERE expires_at < NOW() AND used_at IS NULL';

        $stmt = $this->pdo->query($sql);

        return $stmt->rowCount();
    }
}
