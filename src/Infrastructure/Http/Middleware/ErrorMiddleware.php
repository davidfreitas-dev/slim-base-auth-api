<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\AuthorizationException;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Throwable;

class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly bool $displayErrors,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            return $this->handleException($throwable, $request);
        }
    }

    private function handleException(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
        ]);

        $errorMappings = [
            ValidationException::class => StatusCodeInterface::STATUS_BAD_REQUEST,
            NotFoundException::class => StatusCodeInterface::STATUS_NOT_FOUND,
            AuthenticationException::class => StatusCodeInterface::STATUS_UNAUTHORIZED,
            AuthorizationException::class => StatusCodeInterface::STATUS_FORBIDDEN,
            ConflictException::class => StatusCodeInterface::STATUS_CONFLICT,
            HttpBadRequestException::class => StatusCodeInterface::STATUS_BAD_REQUEST,
            HttpNotFoundException::class => StatusCodeInterface::STATUS_NOT_FOUND,
            HttpUnauthorizedException::class => StatusCodeInterface::STATUS_UNAUTHORIZED,
            HttpForbiddenException::class => StatusCodeInterface::STATUS_FORBIDDEN,
            HttpMethodNotAllowedException::class => StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED,
            HttpInternalServerErrorException::class => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
        ];

        $statusCode = $errorMappings[$e::class] ?? StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR;
        $message = $e->getMessage();
        $data = null;

        if ($e instanceof ValidationException) {
            $data['errors'] = $e->getErrors();
        }

        if ($statusCode >= 500) {
            if (!$this->displayErrors) {
                $message = 'Ocorreu um erro interno no servidor.';
            } else {
                $data['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => \explode("\n", $e->getTraceAsString()),
                ];
            }

            return $this->jsonResponseFactory->error($message, $data, $statusCode);
        }

        return $this->jsonResponseFactory->fail($data, $message, $statusCode);
    }
}
