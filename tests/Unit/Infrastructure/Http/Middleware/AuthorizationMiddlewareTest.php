<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware\AuthorizationMiddleware;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\TestCase;

#[CoversClass(AuthorizationMiddleware::class)]
class AuthorizationMiddlewareTest extends TestCase
{
    private JsonResponseFactory&MockObject $jsonResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
    }

    public function testProcessWithAllowedRole(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $request->method('getAttribute')->with('user_role')->willReturn('admin');
        $handler->method('handle')->with($request)->willReturn($response);

        $middleware = new AuthorizationMiddleware(['admin', 'moderator'], $this->jsonResponseFactory);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessWithNotAllowedRole(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getAttribute')->with('user_role')->willReturn('user');
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Forbidden: Insufficient permissions.', 403);

        $middleware = new AuthorizationMiddleware(['admin', 'moderator'], $this->jsonResponseFactory);
        $middleware->process($request, $handler);
    }

    public function testProcessWithoutUserRole(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getAttribute')->with('user_role')->willReturn(null);
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Authorization check failed. User role not found in token.', 403);

        $middleware = new AuthorizationMiddleware(['admin'], $this->jsonResponseFactory);
        $middleware->process($request, $handler);
    }

    public function testProcessWithNoAllowedRolesConfigured(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getAttribute')->with('user_role')->willReturn('admin');
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Authorization check failed. Allowed roles not configured for this route.', 500);

        $middleware = new AuthorizationMiddleware([], $this->jsonResponseFactory);
        $middleware->process($request, $handler);
    }
}
