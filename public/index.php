<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseFactoryInterface;

require dirname(__DIR__) . '/config/bootstrap.php';

// Build PHP-DI Container
$containerBuilder = new ContainerBuilder();

// Set up settings
$containerBuilder->addDefinitions(APP_ROOT . '/config/settings.php');

// Add container definitions
$containerBuilder->addDefinitions(APP_ROOT . '/config/container.php');

// Build container
$container = $containerBuilder->build();

// Create App instance
AppFactory::setContainer($container);

$app = AppFactory::create();

// Register the response factory
$container->set(ResponseFactoryInterface::class, $app->getResponseFactory());

// Register middleware
$middleware = require APP_ROOT . '/config/middleware.php';

$middleware($app);

// Register routes
$routes = require APP_ROOT . '/config/routes.php';

$routes($app);

// Run app
$app->run();
