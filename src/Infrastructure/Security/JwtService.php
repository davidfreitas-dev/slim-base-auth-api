<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Enum\JwtTokenType;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Redis\RedisCache;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Ramsey\Uuid\Uuid;

class JwtService
{
    private readonly string $privateKey;
    private readonly string $publicKey;

    public function __construct(
        string $privateKeyPath,
        string $publicKeyPath,
        private readonly string $algorithm,
        private readonly int $accessTokenExpire,
        private readonly int $refreshTokenExpire,
        private readonly RedisCache $cache,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        $this->privateKey = \file_get_contents($privateKeyPath);
        $this->publicKey = \file_get_contents($publicKeyPath);
    }

    public function generateAccessToken(int $userId, string $email): string
    {
        $now = \time();
        $jti = Uuid::uuid4()->toString();

        $user = $this->userRepository->findById($userId);
        if (!$user instanceof \App\Domain\Entity\User) {
            throw new AuthenticationException('Usuário não encontrado.. for JWT generation');
        }

        $payload = [
            'iss' => $_ENV['APP_URL'],
            'aud' => $_ENV['APP_URL'],
            'iat' => $now,
            'exp' => $now + $this->accessTokenExpire,
            'jti' => $jti,
            'sub' => $userId,
            'email' => $email,
            'role' => $user->getRole()->getName(),
            'is_verified' => $user->isVerified(),
            'type' => JwtTokenType::ACCESS->value,
        ];

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    public function generateRefreshToken(int $userId): string
    {
        $now = \time();
        $jti = Uuid::uuid4()->toString();

        $user = $this->userRepository->findById($userId);
        if (!$user instanceof \App\Domain\Entity\User) {
            throw new AuthenticationException('Usuário não encontrado.. for JWT generation');
        }

        $payload = [
            'iss' => $_ENV['APP_URL'],
            'aud' => $_ENV['APP_URL'],
            'iat' => $now,
            'exp' => $now + $this->refreshTokenExpire,
            'jti' => $jti,
            'sub' => $userId,
            'role' => $user->getRole()->getName(),
            'type' => JwtTokenType::REFRESH->value,
        ];

        $token = JWT::encode($payload, $this->privateKey, $this->algorithm);

        // Store refresh token in Redis
        $this->cache->set(
            'refresh_token:' . $jti,
            $userId,
            $this->refreshTokenExpire,
        );
        $this->cache->sAdd('user_refresh_tokens:' . $userId, $jti);

        return $token;
    }

    public function validateToken(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algorithm));

            if ($this->isTokenBlocked($decoded->jti)) {
                throw new AuthenticationException('Token has been invalidated');
            }

            return $decoded;
        } catch (ExpiredException $e) {
            throw new AuthenticationException('Token has expired', 401, $e);
        } catch (SignatureInvalidException $e) {
            throw new AuthenticationException('Invalid token signature', 401, $e);
        } catch (Exception $e) {
            throw new AuthenticationException('Invalid token', 401, $e);
        }
    }

    public function blockToken(string $jti, int $expiresAt): void
    {
        $ttl = $expiresAt - \time();
        if ($ttl > 0) {
            $this->cache->set('blocked_token:' . $jti, 1, $ttl);
        }
    }

    public function isTokenBlocked(string $jti): bool
    {
        return $this->cache->has('blocked_token:' . $jti);
    }

    public function revokeRefreshToken(string $jti): void
    {
        $userId = $this->cache->get('refresh_token:' . $jti);
        if ($userId) {
            $this->cache->sRem('user_refresh_tokens:' . $userId, $jti);
        }

        $this->cache->delete('refresh_token:' . $jti);
    }

    public function invalidateAllUserRefreshTokens(int $userId): void
    {
        $userRefreshTokensKey = 'user_refresh_tokens:' . $userId;
        $jtis = $this->cache->sMembers($userRefreshTokensKey);

        foreach ($jtis as $jti) {
            $this->cache->delete('refresh_token:' . $jti);
        }

        $this->cache->delete($userRefreshTokensKey);
    }

    public function isRefreshTokenValid(string $jti): bool
    {
        return $this->cache->has('refresh_token:' . $jti);
    }

    public function getAccessTokenExpire(): int
    {
        return $this->accessTokenExpire;
    }

    public function getRefreshTokenExpire(): int
    {
        return $this->refreshTokenExpire;
    }
}
