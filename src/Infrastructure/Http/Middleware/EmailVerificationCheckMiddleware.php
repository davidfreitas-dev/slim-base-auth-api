<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exception\AuthorizationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use UnexpectedValueException;

class EmailVerificationCheckMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware or request handler.
     *
     * @param Request        $request The request.
     * @param RequestHandler $handler The request handler.
     *
     * @throws AuthorizationException If the user's email is not verified.
     *
     * @return Response The response.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $jwt = $request->getAttribute('token'); // Assuming 'token' attribute holds the decoded JWT

        if (!$jwt) {
            // This middleware should ideally run after JwtAuthMiddleware
            // If no token is found, let the request proceed, JwtAuthMiddleware or other auth mechanisms
            // should handle unauthenticated requests.
            return $handler->handle($request);
        }

        try {
            if (!isset($jwt->is_verified)) {
                throw new UnexpectedValueException('JWT payload missing is_verified claim');
            }

            if (!$jwt->is_verified) {
                throw new AuthorizationException('Email not verified. Access to this resource is restricted.', 403);
            }
        } catch (UnexpectedValueException $unexpectedValueException) {
            // Log the error for debugging purposes if a claim is missing
            // For now, rethrow as AuthorizationException
            throw new AuthorizationException('Authentication error: ' . $unexpectedValueException->getMessage(), 403);
        }

        return $handler->handle($request);
    }
}
