<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use App\Infrastructure\Persistence\Redis\RedisCache;
use App\Infrastructure\Security\JwtService;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\UriInterface;
use Slim\Psr7\Response;
use stdClass;

/**
 * @covers \App\Infrastructure\Http\Middleware\RateLimitMiddleware
 */
final class RateLimitMiddlewareTest extends TestCase
{
    private array $defaultSettings = [
        'enabled' => true,
        'max_requests' => 5,
        'window' => 60, // seconds
    ];

    // ✅ Helper: Create middleware with custom settings
    private function createMiddleware(RedisCache $redisCache, JwtService $jwtService, array $settings = []): RateLimitMiddleware
    {
        return new RateLimitMiddleware(
            $redisCache,
            $jwtService,
            array_merge($this->defaultSettings, $settings),
        );
    }

    // ✅ Helper: Create mocked request
    private function createRequestMock(
        string $method = 'GET',
        string $uriString = '/',
        array $headers = [],
        array $serverParams = [],
        ?stdClass $token = null
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        // Mock getUri()
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($uriString);
        $request->method('getUri')->willReturn($uri);

        $request->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($headers) {
                return $headers[$name] ?? '';
            });

        $defaultServerParams = ['REMOTE_ADDR' => '127.0.0.1'];
        $fullServerParams = array_merge($defaultServerParams, $serverParams);
        $request->method('getServerParams')->willReturn($fullServerParams);

        if ($token !== null) {
            $request->method('getAttribute')->with('token')->willReturn($token);
        } else {
            $request->method('getAttribute')
                ->willReturnCallback(function ($name) use ($token) {
                    if ($name === 'token') {
                        return $token;
                    }
                    return null;
                });
        }

        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    public function testMiddlewareDisabled(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService, ['enabled' => false]);
        $request = $this->createRequestMock();
        $expectedResponse = new Response();

        $requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($expectedResponse);

        $redisCache->expects($this->never())->method('get');
        $redisCache->expects($this->never())->method('set');

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testFirstRequestInWindow(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $request = $this->createRequestMock();
        $initialResponse = new Response();

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(null);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:ip:127.0.0.1', 1, 60);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn($initialResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertGreaterThanOrEqual(time() + 50, (int)$response->getHeaderLine('X-RateLimit-Reset'));
    }

    public function testSubsequentRequestsWithinLimit(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $request = $this->createRequestMock();
        $initialResponse = new Response();

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(2);

        $redisCache->expects($this->exactly(2))
            ->method('ttl')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(30);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:ip:127.0.0.1', 3, 30);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn($initialResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('2', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertGreaterThanOrEqual(time() + 20, (int)$response->getHeaderLine('X-RateLimit-Reset'));
    }

    public function testExceedingRateLimit(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $request = $this->createRequestMock();

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(5);

        $redisCache->expects($this->once())
            ->method('ttl')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(10);

        $requestHandler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $requestHandler);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertGreaterThanOrEqual(time() + 0, (int)$response->getHeaderLine('X-RateLimit-Reset'));
        
        $responseData = json_decode((string)$response->getBody(), true);
        self::assertArrayHasKey('error', $responseData);
        self::assertSame('Too Many Requests', $responseData['error']);
    }

    public function testRateLimitWithAuthenticatedUser(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $token = 'some.jwt.token';
        $userId = 123;
        $decodedToken = (object)['sub' => $userId];

        $request = $this->createRequestMock('GET', '/', ['Authorization' => 'Bearer ' . $token]);

        $jwtService->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($decodedToken);

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:user:' . $userId)
            ->willReturn(null);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:user:' . $userId, 1, 60);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $middleware->process($request, $requestHandler);
        
        self::assertTrue(true);
    }

    public function testRateLimitFallsBackToIpWhenInvalidToken(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $token = 'invalid.jwt.token';

        $request = $this->createRequestMock('GET', '/', ['Authorization' => 'Bearer ' . $token]);

        $jwtService->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willThrowException(new Exception('Invalid Token'));

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:127.0.0.1')
            ->willReturn(null);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:ip:127.0.0.1', 1, 60);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $middleware->process($request, $requestHandler);
        
        self::assertTrue(true);
    }

    public function testIdentifierFromXForwardedFor(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $clientIp = '192.168.1.100';
        $request = $this->createRequestMock('GET', '/', [], ['HTTP_X_FORWARDED_FOR' => $clientIp . ', 10.0.0.1']);

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:' . $clientIp)
            ->willReturn(null);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:ip:' . $clientIp, 1, 60);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $middleware->process($request, $requestHandler);
        
        self::assertTrue(true);
    }

    public function testIdentifierFallsBackToRemoteAddr(): void
    {
        $redisCache = $this->createMock(RedisCache::class);
        $jwtService = $this->createMock(JwtService::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMiddleware($redisCache, $jwtService);
        $remoteAddr = '172.16.0.1';
        $request = $this->createRequestMock('GET', '/', [], ['REMOTE_ADDR' => $remoteAddr]);

        $redisCache->expects($this->once())
            ->method('get')
            ->with('rate_limit:ip:' . $remoteAddr)
            ->willReturn(null);

        $redisCache->expects($this->once())
            ->method('set')
            ->with('rate_limit:ip:' . $remoteAddr, 1, 60);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $middleware->process($request, $requestHandler);
        
        self::assertTrue(true);
    }
}