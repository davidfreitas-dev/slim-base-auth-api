<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Decorator;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Redis\RedisCache;
use Psr\Log\LoggerInterface;

class CachingUserRepository implements UserRepositoryInterface
{
    private const CACHE_PREFIX_ID = 'user:id:';

    private const CACHE_PREFIX_EMAIL = 'user:email:';

    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly UserRepositoryInterface $decoratedRepository,
        private readonly RedisCache $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function findById(int $id): ?User
    {
        $cacheKey = self::CACHE_PREFIX_ID . $id;

        // Try to get from cache first
        $cachedUser = $this->cache->get($cacheKey);
        if ($cachedUser) {
            $this->logger->info('User cache hit for ID: ' . $id);

            return User::fromArray(\json_decode((string) $cachedUser, true));
        }

        $this->logger->info('User cache miss for ID: ' . $id);

        // If not in cache, get from original repository
        $user = $this->decoratedRepository->findById($id);

        // Store in cache for future requests
        if ($user instanceof \App\Domain\Entity\User) {
            $this->setCache($user);
        }

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        $cacheKey = self::CACHE_PREFIX_EMAIL . $email;

        // Try to get from cache first
        $cachedUser = $this->cache->get($cacheKey);
        if ($cachedUser) {
            $this->logger->info('User cache hit for email: ' . $email);

            return User::fromArray(\json_decode((string) $cachedUser, true));
        }

        $this->logger->info('User cache miss for email: ' . $email);

        // If not in cache, get from original repository
        $user = $this->decoratedRepository->findByEmail($email);

        // Store in cache for future requests
        if ($user instanceof \App\Domain\Entity\User) {
            $this->setCache($user);
        }

        return $user;
    }

    public function create(User $user): User
    {
        // For create, we just pass through. Caching will occur on the first findById/findByEmail.
        return $this->decoratedRepository->create($user);
    }

    public function update(User $user): User
    {
        // Fetch the user's current state BEFORE the update, to get old identifiers
        $oldUser = $this->findById($user->getId());

        // Perform the actual update in the database
        $updatedUser = $this->decoratedRepository->update($user);

        // Invalidate the cache for the old user's data (especially old email key)
        if ($oldUser instanceof \App\Domain\Entity\User) {
            $this->invalidateCache($oldUser);
        }

        // Proactively cache the NEW, updated user object
        $this->setCache($updatedUser);
        $this->logger->info('Proactively re-cached user after update for ID: ' . $updatedUser->getId());

        return $updatedUser;
    }

    public function delete(int $id): bool
    {
        // We need to fetch the user first to get all identifiers (like email) for cache invalidation
        $userToDelete = $this->findById($id);

        if (!$userToDelete instanceof \App\Domain\Entity\User) {
            return false; // User doesn't exist
        }

        $deleted = $this->decoratedRepository->delete($id);

        if ($deleted) {
            $this->invalidateCache($userToDelete);
        }

        return $deleted;
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        // Fetch the user's current state BEFORE the update
        $user = $this->findById($userId);
        if ($user instanceof \App\Domain\Entity\User) {
            $this->invalidateCache($user);
        }

        // Perform the update
        $this->decoratedRepository->updatePassword($userId, $newPassword);

        // We don't re-cache here because the password change might affect the user object state
        // It's safer to let the next findById/findByEmail re-cache it fresh from the DB.
    }

    public function markUserAsVerified(int $userId): void
    {
        // Fetch the user's current state BEFORE the update to get their details for cache invalidation
        $user = $this->findById($userId);
        if ($user instanceof \App\Domain\Entity\User) {
            $this->invalidateCache($user);
        }

        // Perform the update in the primary repository
        $this->decoratedRepository->markUserAsVerified($userId);

        $this->logger->info('User marked as verified in DB, cache invalidated for ID: ' . $userId);
    }

    // For methods that are complex to cache, we just pass them through
    // to the original repository without caching.

    public function findByCpfCnpj(string $cpfcnpj): ?User
    {
        return $this->decoratedRepository->findByCpfCnpj($cpfcnpj);
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        return $this->decoratedRepository->findAll($limit, $offset);
    }

    public function count(): int
    {
        return $this->decoratedRepository->count();
    }

    /**
     * Sets the cache entries for a given user.
     */
    private function setCache(User $user): void
    {
        $idCacheKey = self::CACHE_PREFIX_ID . $user->getId();
        $emailCacheKey = self::CACHE_PREFIX_EMAIL . $user->getEmail();

        // For caching, we need the full user object for authentication use cases.
        $data = $user->toArray();
        $data['password'] = $user->getPassword();

        $encodedUser = \json_encode($data);

        $this->cache->set($idCacheKey, $encodedUser, self::CACHE_TTL);
        $this->cache->set($emailCacheKey, $encodedUser, self::CACHE_TTL);
    }

    /**
     * Invalidates cache entries for a given user.
     */
    private function invalidateCache(User $user): void
    {
        $idCacheKey = self::CACHE_PREFIX_ID . $user->getId();
        $emailCacheKey = self::CACHE_PREFIX_EMAIL . $user->getEmail();

        $this->cache->delete($idCacheKey);
        $this->cache->delete($emailCacheKey);

        $this->logger->info('Invalidated cache for user ID: ' . $user->getId());
    }
}
