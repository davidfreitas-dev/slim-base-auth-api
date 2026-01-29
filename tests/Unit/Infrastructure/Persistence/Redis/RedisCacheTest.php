<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Persistence\Redis;

use App\Infrastructure\Persistence\Redis\RedisCache;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * @covers \App\Infrastructure\Persistence\Redis\RedisCache
 */
final class RedisCacheTest extends TestCase
{
    private Redis $redisMock;
    private RedisCache $redisCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redisMock = $this->createMock(Redis::class);
        $this->redisCache = new RedisCache($this->redisMock);
    }

    public function testGetCacheHit(): void
    {
        $key = 'test_key';
        $value = ['foo' => 'bar'];
        $serializedValue = \serialize($value);

        $this->redisMock->method('get')
            ->with($key)
            ->willReturn($serializedValue);

        $result = $this->redisCache->get($key);

        self::assertEquals($value, $result);
    }

    public function testGetCacheMiss(): void
    {
        $key = 'test_key';

        $this->redisMock->method('get')
            ->with($key)
            ->willReturn(false); // Redis returns false on cache miss

        $result = $this->redisCache->get($key);

        self::assertNull($result);
    }

    public function testSetWithoutTtl(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $serializedValue = \serialize($value);

        $this->redisMock->method('set')
            ->with($key, $serializedValue)
            ->willReturn(true);

        $result = $this->redisCache->set($key, $value);

        self::assertTrue($result);
    }

    public function testSetWithTtl(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 3600;
        $serializedValue = \serialize($value);

        $this->redisMock->method('setex')
            ->with($key, $ttl, $serializedValue)
            ->willReturn(true);

        $result = $this->redisCache->set($key, $value, $ttl);

        self::assertTrue($result);
    }

    public function testHasKeyExists(): void
    {
        $key = 'test_key';

        $this->redisMock->method('exists')
            ->with($key)
            ->willReturn(1); // Returns number of keys that exist

        $result = $this->redisCache->has($key);

        self::assertTrue($result);
    }

    public function testHasKeyDoesNotExist(): void
    {
        $key = 'test_key';

        $this->redisMock->method('exists')
            ->with($key)
            ->willReturn(0);

        $result = $this->redisCache->has($key);

        self::assertFalse($result);
    }

    public function testDeleteKeyExists(): void
    {
        $key = 'test_key';

        $this->redisMock->method('del')
            ->with($key)
            ->willReturn(1); // Returns number of keys deleted

        $result = $this->redisCache->delete($key);

        self::assertTrue($result);
    }

    public function testDeleteKeyDoesNotExist(): void
    {
        $key = 'test_key';

        $this->redisMock->method('del')
            ->with($key)
            ->willReturn(0);

        $result = $this->redisCache->delete($key);

        self::assertFalse($result);
    }

    public function testExpire(): void
    {
        $key = 'test_key';
        $ttl = 60;

        $this->redisMock->method('expire')
            ->with($key, $ttl)
            ->willReturn(true);

        $result = $this->redisCache->expire($key, $ttl);

        self::assertTrue($result);
    }

    public function testTtlKeyExists(): void
    {
        $key = 'test_key';
        $expectedTtl = 300;

        $this->redisMock->method('ttl')
            ->with($key)
            ->willReturn($expectedTtl);

        $result = $this->redisCache->ttl($key);

        self::assertEquals($expectedTtl, $result);
    }

    public function testTtlKeyDoesNotExist(): void
    {
        $key = 'test_key';

        $this->redisMock->method('ttl')
            ->with($key)
            ->willReturn(-2); // Redis returns -2 for non-existent key

        $result = $this->redisCache->ttl($key);

        self::assertEquals(-2, $result);
    }

    public function testTtlKeyHasNoExpiry(): void
    {
        $key = 'test_key';

        $this->redisMock->method('ttl')
            ->with($key)
            ->willReturn(-1); // Redis returns -1 for key with no expiry

        $result = $this->redisCache->ttl($key);

        self::assertEquals(-1, $result);
    }

    public function testFlush(): void
    {
        $this->redisMock->method('flushDB')
            ->willReturn(true);

        $result = $this->redisCache->flush();

        self::assertTrue($result);
    }

    public function testSAdd(): void
    {
        $key = 'test_set';
        $members = ['member1', 'member2'];

        $this->redisMock->method('sAdd')
            ->with($key, ...$members)
            ->willReturn(2); // Number of members added

        $result = $this->redisCache->sAdd($key, ...$members);

        self::assertEquals(2, $result);
    }

    public function testSMembers(): void
    {
        $key = 'test_set';
        $members = ['member1', 'member2'];

        $this->redisMock->method('sMembers')
            ->with($key)
            ->willReturn($members);

        $result = $this->redisCache->sMembers($key);

        self::assertEquals($members, $result);
    }

    public function testSRem(): void
    {
        $key = 'test_set';
        $members = ['member1'];

        $this->redisMock->method('sRem')
            ->with($key, ...$members)
            ->willReturn(1); // Number of members removed

        $result = $this->redisCache->sRem($key, ...$members);

        self::assertEquals(1, $result);
    }
}
