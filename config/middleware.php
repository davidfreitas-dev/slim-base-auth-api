<?php

declare(strict_types=1);

use App\Infrastructure\Http\Middleware\CorsMiddleware;
use App\Infrastructure\Http\Middleware\ErrorMiddleware;
use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use Slim\App;

return function (App $app): void {
    // Parse JSON, form data and XML
    $app->addBodyParsingMiddleware();

    // Add routing middleware
    $app->addRoutingMiddleware();

    // CORS Middleware (must be first)
    $app->add(CorsMiddleware::class);

    // Rate Limiting Middleware
    $app->add(RateLimitMiddleware::class);

    // Custom Error Middleware
    $app->add(ErrorMiddleware::class);

    // Error handling middleware (must be last)
    $errorMiddleware = $app->addErrorMiddleware(
        $app->getContainer()->get('settings')['displayErrorDetails'],
        true,
        true,
    );
};
