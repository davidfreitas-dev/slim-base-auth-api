<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Redis;

use Redis;

class RedisCache
{
    public function __construct(private readonly Redis $redis)
    {
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return null;
        }

        return \unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $serialized = \serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        }

        return $this->redis->set($key, $serialized);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function expire(string $key, int $ttl): bool
    {
        return $this->redis->expire($key, $ttl);
    }

    public function ttl(string $key): int
    {
        $ttl = $this->redis->ttl($key);

        return $ttl !== false ? $ttl : -2;
    }

    public function flush(): bool
    {
        return $this->redis->flushDB();
    }

    /**
     * Add one or more members to a set.
     */
    public function sAdd(string $key, mixed ...$members): int
    {
        return $this->redis->sAdd($key, ...$members);
    }

    /**
     * Get all the members in a set.
     */
    public function sMembers(string $key): array
    {
        return $this->redis->sMembers($key);
    }

    /**
     * Remove one or more members from a set.
     */
    public function sRem(string $key, mixed ...$members): int
    {
        return $this->redis->sRem($key, ...$members);
    }
}
