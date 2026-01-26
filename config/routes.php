<?php

declare(strict_types=1);

use App\Infrastructure\Http\Middleware\AuthorizationMiddleware;
use App\Infrastructure\Http\Middleware\EmailVerificationCheckMiddleware;
use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use App\Presentation\Api\V1\Controller\AdminController;
use App\Presentation\Api\V1\Controller\AuthController;
use App\Presentation\Api\V1\Controller\ErrorLogController;
use App\Presentation\Api\V1\Controller\HealthController;
use App\Presentation\Api\V1\Controller\UserController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $container = $app->getContainer();

    $app->get('/health', [HealthController::class, 'check']);

    $app->group('/api/v1', function (RouteCollectorProxy $group) use ($container): void {
        $group->group('/auth', function (RouteCollectorProxy $auth): void {
            $auth->post('/register', [AuthController::class, 'register']);
            $auth->post('/login', [AuthController::class, 'login']);
            $auth->post('/refresh', [AuthController::class, 'refresh']);
            $auth->post('/forgot-password', [AuthController::class, 'forgotPassword']);
            $auth->post('/validate-reset-token', [AuthController::class, 'validateResetToken']);
            $auth->post('/reset-password', [AuthController::class, 'resetPassword']);
            $auth->get('/verify-email', [AuthController::class, 'verifyEmail']);
            $auth->post('/logout', [AuthController::class, 'logout'])->add(JwtAuthMiddleware::class);
        })->add(RateLimitMiddleware::class);

        $group->group('/profile', function (RouteCollectorProxy $profileGroup): void {
            $profileGroup->get('', [UserController::class, 'get']);
            $profileGroup->put('', [UserController::class, 'update']);
            $profileGroup->patch('/change-password', [UserController::class, 'changePassword']);
            $profileGroup->delete('', [UserController::class, 'delete']);
        })->add(EmailVerificationCheckMiddleware::class)
          ->add(JwtAuthMiddleware::class);

        // Admin-only User Management routes
        $group->group('/users', function (RouteCollectorProxy $usersGroup): void {
            $usersGroup->post('', [AdminController::class, 'createUser']);
            $usersGroup->get('', [AdminController::class, 'listUsers']);
            $usersGroup->get('/{id:[0-9]+}', [AdminController::class, 'getUser']);
            $usersGroup->put('/{id:[0-9]+}', [AdminController::class, 'updateUser']);
            $usersGroup->delete('/{id:[0-9]+}', [AdminController::class, 'deleteUser']);
        })->add(new AuthorizationMiddleware(['admin'], $container->get(\App\Infrastructure\Http\Response\JsonResponseFactory::class)))
          ->add(EmailVerificationCheckMiddleware::class)
          ->add(JwtAuthMiddleware::class);

        // Admin-only Error Log Management routes
        $group->group('/error-logs', function (RouteCollectorProxy $errorLogsGroup): void {
            $errorLogsGroup->get('', [ErrorLogController::class, 'listErrorLogs']);
            $errorLogsGroup->get('/{id:[0-9]+}', [ErrorLogController::class, 'getErrorLogDetails']);
            $errorLogsGroup->patch('/{id:[0-9]+}/resolve', [ErrorLogController::class, 'resolveErrorLog']);
        })->add(new AuthorizationMiddleware(['admin'], $container->get(\App\Infrastructure\Http\Response\JsonResponseFactory::class)))
          ->add(EmailVerificationCheckMiddleware::class)
          ->add(JwtAuthMiddleware::class);
    });
};
