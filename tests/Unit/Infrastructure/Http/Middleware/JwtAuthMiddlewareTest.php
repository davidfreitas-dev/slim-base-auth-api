<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Middleware;

use App\Domain\Entity\User;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Infrastructure\Security\JwtService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\TestCase;

#[CoversClass(JwtAuthMiddleware::class)]
class JwtAuthMiddlewareTest extends TestCase
{
    private JwtService&MockObject $jwtService;
    private JsonResponseFactory&MockObject $jsonResponseFactory;
    private UserRepositoryInterface&MockObject $userRepository;
    private JwtAuthMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = $this->createMock(JwtService::class);
        $this->jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->middleware = new JwtAuthMiddleware($this->jwtService, $this->jsonResponseFactory, $this->userRepository);
    }

    public function testProcessWithValidToken(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $user = $this->createMock(User::class);

        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer valid_token');

        $decodedToken = (object) [
            'sub' => 1,
            'email' => 'test@example.com',
            'role' => 'user',
            'jti' => '123',
            'exp' => time() + 3600,
            'type' => 'access',
        ];

        $this->jwtService->method('validateToken')->with('valid_token')->willReturn($decodedToken);
        $this->userRepository->method('findById')->with(1)->willReturn($user);

        $request->expects($this->exactly(5))->method('withAttribute')->willReturn($request);
        $handler->method('handle')->with($request)->willReturn($response);

        $this->middleware->process($request, $handler);
    }

    public function testProcessWithoutAuthorizationHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getHeaderLine')->with('Authorization')->willReturn('');
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Cabeçalho de Autorização inválido', 401);

        $this->middleware->process($request, $handler);
    }

    public function testProcessWithInvalidAuthorizationHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getHeaderLine')->with('Authorization')->willReturn('Invalid header');
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Cabeçalho de Autorização inválido', 401);

        $this->middleware->process($request, $handler);
    }

    public function testProcessWithInvalidToken(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer invalid_token');
        $this->jwtService->method('validateToken')->with('invalid_token')->willThrowException(new AuthenticationException('Invalid token'));
        $this->jsonResponseFactory->expects($this->once())->method('fail')->with(null, 'Invalid token', 401);

        $this->middleware->process($request, $handler);
    }
}
