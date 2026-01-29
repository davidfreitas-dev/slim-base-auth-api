<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private array $settings)
    {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Handle preflight requests
        $response = $request->getMethod() === 'OPTIONS' ? new Response() : $handler->handle($request);

        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = $this->settings['allowed_origins'];

        // Check if origin is allowed
        if (\in_array('*', $allowedOrigins, true) || \in_array($origin, $allowedOrigins, true)) {
            $allowOrigin = \in_array('*', $allowedOrigins, true) ? '*' : $origin;
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader(
                    'Access-Control-Allow-Methods',
                    \implode(', ', $this->settings['allowed_methods']),
                )
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    \implode(', ', $this->settings['allowed_headers']),
                )
                ->withHeader(
                    'Access-Control-Expose-Headers',
                    \implode(', ', $this->settings['exposed_headers']),
                )
                ->withHeader(
                    'Access-Control-Max-Age',
                    (string)$this->settings['max_age'],
                )
            ;

            if ($this->settings['allow_credentials']) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }
}
