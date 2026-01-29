<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Http\Middleware;

use App\Domain\Exception\AuthorizationException;
use App\Infrastructure\Http\Middleware\EmailVerificationCheckMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use stdClass;

/**
 * @covers \App\Infrastructure\Http\Middleware\EmailVerificationCheckMiddleware
 */
final class EmailVerificationCheckMiddlewareTest extends TestCase
{
    private RequestFactory $requestFactory;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestFactory = new RequestFactory();
        $this->responseFactory = new ResponseFactory();
    }

    private function createMiddleware(): EmailVerificationCheckMiddleware
    {
        return new EmailVerificationCheckMiddleware();
    }

    private function createRequest(string $method = 'GET', string $uri = '/protected'): ServerRequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    private function createResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse();
    }

    private function createRequestWithToken(object $jwt): ServerRequestInterface
    {
        return $this->createRequest()->withAttribute('token', $jwt);
    }

    private function createMockedHandler(?ResponseInterface $response = null): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        
        if ($response !== null) {
            $handler->expects($this->once())
                ->method('handle')
                ->willReturn($response);
        }
        
        return $handler;
    }

    public function testProcessReturnsResponseWhenNoJwtToken(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest();
        $response = $this->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($response);

        $resultResponse = $middleware->process($request, $handler);

        self::assertSame($response, $resultResponse);
    }

    public function testProcessReturnsResponseWhenEmailIsVerified(): void
    {
        $middleware = $this->createMiddleware();
        
        $jwt = new stdClass();
        $jwt->is_verified = true;

        $request = $this->createRequestWithToken($jwt);
        $response = $this->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($response);

        $resultResponse = $middleware->process($request, $handler);

        self::assertSame($response, $resultResponse);
    }

    public function testProcessThrowsAuthorizationExceptionWhenEmailIsNotVerified(): void
    {
        $middleware = $this->createMiddleware();
        
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Email not verified. Access to this resource is restricted.');
        $this->expectExceptionCode(403);

        $jwt = new stdClass();
        $jwt->is_verified = false;

        $request = $this->createRequestWithToken($jwt);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware->process($request, $handler);
    }

    public function testProcessThrowsAuthorizationExceptionWhenJwtPayloadMissingIsVerifiedClaim(): void
    {
        $middleware = $this->createMiddleware();
        
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Authentication error: JWT payload missing is_verified claim');
        $this->expectExceptionCode(403);

        $jwt = new stdClass();

        $request = $this->createRequestWithToken($jwt);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware->process($request, $handler);
    }
}