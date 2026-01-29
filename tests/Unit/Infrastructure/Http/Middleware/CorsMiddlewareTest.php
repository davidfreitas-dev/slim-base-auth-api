<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response;

/**
 * @covers \App\Infrastructure\Http\Middleware\CorsMiddleware
 */
final class CorsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function getDefaultSettings(): array
    {
        return [
            'allowed_origins' => ['http://localhost:3000'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['X-Requested-With', 'Content-Type', 'Accept', 'Origin', 'Authorization'],
            'exposed_headers' => ['Content-Length', 'X-Powered-By'],
            'max_age' => 3600,
            'allow_credentials' => true,
        ];
    }

    public function testPreflightRequestWithAllowedOrigin(): void
    {
        $settings = $this->getDefaultSettings();
        $request = (new RequestFactory())
            ->createRequest('OPTIONS', '/test')
            ->withHeader('Origin', 'http://localhost:3000')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Content-Type, Authorization');

        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle'); // Handler should not be called for OPTIONS

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertSame(200, $resultResponse->getStatusCode());
        self::assertSame('http://localhost:3000', $resultResponse->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST, PUT, DELETE, OPTIONS', $resultResponse->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('X-Requested-With, Content-Type, Accept, Origin, Authorization', $resultResponse->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame('3600', $resultResponse->getHeaderLine('Access-Control-Max-Age'));
        self::assertSame('true', $resultResponse->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testSimpleRequestWithAllowedOrigin(): void
    {
        $settings = $this->getDefaultSettings();
        $request = (new RequestFactory())
            ->createRequest('GET', '/test')
            ->withHeader('Origin', 'http://localhost:3000');

        $expectedResponse = (new ResponseFactory())->createResponse()->withStatus(200)->withHeader('X-Test', 'true');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with(self::identicalTo($request))
            ->willReturn($expectedResponse);

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertSame(200, $resultResponse->getStatusCode());
        self::assertSame('http://localhost:3000', $resultResponse->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST, PUT, DELETE, OPTIONS', $resultResponse->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('X-Requested-With, Content-Type, Accept, Origin, Authorization', $resultResponse->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame('3600', $resultResponse->getHeaderLine('Access-Control-Max-Age'));
        self::assertSame('true', $resultResponse->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertSame('true', $resultResponse->getHeaderLine('X-Test')); // Ensure original headers are preserved
    }

    public function testRequestWithWildcardAllowedOrigin(): void
    {
        $settings = array_merge($this->getDefaultSettings(), ['allowed_origins' => ['*']]);
        $request = (new RequestFactory())
            ->createRequest('GET', '/test')
            ->withHeader('Origin', 'http://anydomain.com');

        $expectedResponse = (new ResponseFactory())->createResponse()->withStatus(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expectedResponse);

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertSame('*', $resultResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testRequestWithDisallowedOrigin(): void
    {
        $settings = $this->getDefaultSettings();
        $request = (new RequestFactory())
            ->createRequest('GET', '/test')
            ->withHeader('Origin', 'http://malicious.com');

        $expectedResponse = (new ResponseFactory())->createResponse()->withStatus(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expectedResponse);

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertEmpty($resultResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testRequestWithoutOriginHeader(): void
    {
        $settings = $this->getDefaultSettings();
        $request = (new RequestFactory())
            ->createRequest('GET', '/test');

        $expectedResponse = (new ResponseFactory())->createResponse()->withStatus(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expectedResponse);

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertEmpty($resultResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testNoCredentialsAllowed(): void
    {
        $settings = array_merge($this->getDefaultSettings(), ['allow_credentials' => false]);
        $request = (new RequestFactory())
            ->createRequest('GET', '/test')
            ->withHeader('Origin', 'http://localhost:3000');

        $expectedResponse = (new ResponseFactory())->createResponse()->withStatus(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expectedResponse);

        $middleware = new CorsMiddleware($settings);
        $resultResponse = $middleware->process($request, $handler);

        self::assertEmpty($resultResponse->getHeaderLine('Access-Control-Allow-Credentials'));
    }
}
