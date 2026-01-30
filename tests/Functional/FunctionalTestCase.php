<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Domain\Entity\User;
use App\Infrastructure\Security\JwtService;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Tests\Integration\DatabaseTestCase;

abstract class FunctionalTestCase extends DatabaseTestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApp();
    }

    protected function sendRequest(
        string $method,
        string $path,
        array $body = [],
        array $headers = []
    ): ResponseInterface {
        $factory = $this->app->getContainer()->get(ServerRequestFactoryInterface::class);
        $streamFactory = $this->app->getContainer()->get(StreamFactoryInterface::class);

        $uri = $path;
        $serverParams = [];

        $request = $factory->createServerRequest($method, $uri, $serverParams);

        if ($body !== []) {
            $request = $request->withParsedBody($body);
            $request = $request->withBody($streamFactory->createStream(json_encode($body)));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (empty($headers['Content-Type'])) {
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        if (empty($headers['Accept'])) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        $response = $this->app->handle($request);

        if ($response->getStatusCode() >= 500) {
            echo (string) $response->getBody();
        }

        return $response;
    }

    protected function generateTokenForUser(User $user): string
    {
        $jwtService = $this->app->getContainer()->get(JwtService::class);
        return $jwtService->generateAccessToken($user->getId(), $user->getEmail());
    }

    private function createApp(): App
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->addDefinitions(APP_ROOT . '/config/settings.php');
        $containerBuilder->addDefinitions(APP_ROOT . '/config/container.php');

        $container = $containerBuilder->build();

        AppFactory::setContainer($container);

        $app = AppFactory::create();

        $container->set(ResponseFactoryInterface::class, $app->getResponseFactory());

        $middleware = require APP_ROOT . '/config/middleware.php';
        $middleware($app);

        $routes = require APP_ROOT . '/config/routes.php';
        $routes($app);

        return $app;
    }

    protected function sendRequestWithFile(
        string $method,
        string $path,
        array $body = [],
        array $headers = [],
        array $files = []
    ): ResponseInterface {
        $factory = $this->app->getContainer()->get(ServerRequestFactoryInterface::class);
    
        $uri = $path;
        $serverParams = [];
    
        $request = $factory->createServerRequest($method, $uri, $serverParams);
    
        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }
    
        if (!empty($files)) {
            $request = $request->withUploadedFiles($files);
        }
    
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
    
        // For file uploads, the content type is typically multipart/form-data
        // and is handled by the HTTP client/server, so we don't set it manually here.
    
        $response = $this->app->handle($request);
    
        if ($response->getStatusCode() >= 500) {
            echo (string) $response->getBody();
        }
    
        return $response;
    }
}