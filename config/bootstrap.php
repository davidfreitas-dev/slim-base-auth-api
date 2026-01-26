<?php

declare(strict_types=1);

use Dotenv\Dotenv;

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(APP_ROOT);
$dotenv->load();
