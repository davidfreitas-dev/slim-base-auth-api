<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Http\Middleware;

use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\AuthorizationException;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Middleware\ErrorMiddleware;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use Fig\Http\Message\StatusCodeInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response as SlimResponse;

/**
 * @covers \App\Infrastructure\Http\Middleware\ErrorMiddleware
 */
final class ErrorMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function getRequestMock(RequestFactory $requestFactory, string $method = 'GET', string $uri = '/'): ServerRequestInterface
    {
        return $requestFactory->createRequest($method, $uri);
    }

    private function getJsonResponseMock(ResponseFactory $responseFactory, int $statusCode, string $message, ?array $data = null): ResponseInterface
    {
        $response = $responseFactory->createResponse()->withStatus($statusCode);
        $response->getBody()->write(\json_encode(['status' => 'error', 'message' => $message, 'data' => $data]));

        return $response;
    }

    public function testProcessHandlesRequestSuccessfully(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $expectedResponse = $responseFactory->createResponse(200);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($expectedResponse);

        $logger->expects($this->never())->method('error');
        $jsonResponseFactory->expects($this->never())->method('error');
        $jsonResponseFactory->expects($this->never())->method('fail');

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesValidationException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $validationErrors = ['field' => ['error message']];
        $exception = new ValidationException('Validation failed', $validationErrors);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Validation failed',
                $this->callback(function (array $context) use ($exception) {
                    return $context['exception'] === $exception::class;
                }),
            );

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_BAD_REQUEST,
            'Validation failed',
            ['errors' => $validationErrors],
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                ['errors' => $validationErrors],
                'Validation failed',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesNotFoundException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new NotFoundException('Resource not found');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_NOT_FOUND,
            'Resource not found',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Resource not found',
                StatusCodeInterface::STATUS_NOT_FOUND,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesAuthenticationException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new AuthenticationException('Authentication required');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_UNAUTHORIZED,
            'Authentication required',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Authentication required',
                StatusCodeInterface::STATUS_UNAUTHORIZED,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesAuthorizationException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new AuthorizationException('Forbidden access');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_FORBIDDEN,
            'Forbidden access',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Forbidden access',
                StatusCodeInterface::STATUS_FORBIDDEN,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesConflictException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new ConflictException('Conflict detected');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_CONFLICT,
            'Conflict detected',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Conflict detected',
                StatusCodeInterface::STATUS_CONFLICT,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpBadRequestException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpBadRequestException($request, 'Bad request');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_BAD_REQUEST,
            'Bad request',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Bad request',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpNotFoundException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpNotFoundException($request, 'Not found');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_NOT_FOUND,
            'Not found',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Not found',
                StatusCodeInterface::STATUS_NOT_FOUND,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpUnauthorizedException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpUnauthorizedException($request, 'Unauthorized');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_UNAUTHORIZED,
            'Unauthorized',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Unauthorized',
                StatusCodeInterface::STATUS_UNAUTHORIZED,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpForbiddenException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpForbiddenException($request, 'Forbidden');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_FORBIDDEN,
            'Forbidden',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Forbidden',
                StatusCodeInterface::STATUS_FORBIDDEN,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpMethodNotAllowedException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpMethodNotAllowedException($request, 'Method not allowed', null, ['POST']);

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED,
            'Method not allowed',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(
                null,
                'Method not allowed',
                StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesSlimHttpInternalServerErrorException(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new HttpInternalServerErrorException($request, 'Internal server error');

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            'Internal server error',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Internal server error',
                $this->callback(function (?array $data) {
                    return isset($data['debug']['file'], $data['debug']['line'], $data['debug']['trace']);
                }),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesGenericThrowableWithDisplayErrorsTrue(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, true);
        $request = $this->getRequestMock($requestFactory);
        $exception = new class ('Generic error', 0) extends \Exception {
        };

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            'Generic error',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Generic error',
                $this->callback(function (?array $data) {
                    return isset($data['debug']['file'], $data['debug']['line'], $data['debug']['trace']);
                }),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }

    public function testProcessHandlesGenericThrowableWithDisplayErrorsFalse(): void
    {
        $requestFactory = new RequestFactory();
        $responseFactory = new ResponseFactory();

        $logger = $this->createMock(LoggerInterface::class);
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new ErrorMiddleware($logger, $jsonResponseFactory, false);
        $request = $this->getRequestMock($requestFactory);
        $exception = new class ('Generic error', 0) extends \Exception {
        };

        $requestHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger->expects($this->once())->method('error');

        $expectedResponse = $this->getJsonResponseMock(
            $responseFactory,
            StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            'Ocorreu um erro interno no servidor.',
        );
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Ocorreu um erro interno no servidor.',
                null,
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            )
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $requestHandler);

        self::assertSame($expectedResponse, $response);
    }
}