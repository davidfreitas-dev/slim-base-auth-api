<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Persistence\Redis\RedisCache;
use App\Infrastructure\Security\JwtService;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedisCache $cache,
        private readonly JwtService $jwtService,
        private array $settings,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->settings['enabled']) {
            return $handler->handle($request);
        }

        $identifier = $this->getIdentifier($request);
        $key = 'rate_limit:' . $identifier;

        $maxRequests = $this->settings['max_requests'];
        $window = $this->settings['window'];

        // Get current count
        $current = $this->cache->get($key);

        if ($current === null) {
            // First request in this window
            $this->cache->set($key, 1, $window);
            $remaining = $maxRequests - 1;
            $resetTime = \time() + $window;
        } else {
            $remaining = $maxRequests - $current;
            $resetTime = \time() + $this->cache->ttl($key);

            if ($current >= $maxRequests) {
                // Rate limit exceeded
                return $this->buildRateLimitResponse($maxRequests, 0, $resetTime);
            }

            // Increment counter
            $this->cache->set($key, (int)$current + 1, $this->cache->ttl($key) ?: $window);
            --$remaining;
        }

        $response = $handler->handle($request);

        // Add rate limit headers
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)\max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string)$resetTime)
        ;
    }

    private function getIdentifier(ServerRequestInterface $request): string
    {
        // Try to get user ID from token (if authenticated)
        $token = $this->extractToken($request);
        if ($token) {
            try {
                $decodedToken = $this->jwtService->validateToken($token);
                if ($decodedToken && isset($decodedToken->sub)) {
                    // Use user ID (sub claim) as identifier
                    return 'user:' . $decodedToken->sub;
                }
            } catch (Exception) {
                // Token is invalid or expired, fall through to IP-based rate limiting
                // We don't log this here as JwtAuthMiddleware will handle logging invalid tokens
            }
        }

        // Fallback to IP address
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? 'unknown';

        // Check for proxy headers
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ip = \explode(',', (string) $serverParams['HTTP_X_FORWARDED_FOR'])[0];
        }

        return 'ip:' . $ip;
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if (\preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function buildRateLimitResponse(int $limit, int $remaining, int $reset): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(\json_encode([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $reset - \time(),
        ]));

        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-RateLimit-Limit', (string)$limit)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset', (string)$reset)
            ->withHeader('Retry-After', (string)($reset - \time()))
        ;
    }
}
