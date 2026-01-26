<?php

declare(strict_types=1);

return [
    'settings' => [
        'displayErrorDetails' => $_ENV['APP_DEBUG'] === 'true',
        'logErrors' => true,
        'logErrorDetails' => true,

        'paths' => [
            'upload_path' => __DIR__ . '/../storage/uploads/profiles',
        ],

        'db' => [
            'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => ($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_HOST'] : $_ENV['DB_HOST']),
            'port' => (int)($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_PORT'] : $_ENV['DB_PORT']),
            'database' => ($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_NAME'] : $_ENV['DB_NAME']),
            'username' => ($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_USER'] : $_ENV['DB_USER']),
            'password' => ($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_PASS'] : $_ENV['DB_PASS']),
            'charset' => ($_ENV['APP_ENV'] === 'testing' ? $_ENV['DB_TEST_CHARSET'] : $_ENV['DB_CHARSET']),
        ],

        'redis' => [
            'host' => $_ENV['REDIS_HOST'],
            'port' => (int)$_ENV['REDIS_PORT'],
            'password' => $_ENV['REDIS_PASSWORD'] ?: null,
            'database' => (int)$_ENV['REDIS_DATABASE'],
        ],

        'jwt' => [
            'private_key_path' => __DIR__ . '/keys/private_key.pem',
            'public_key_path' => __DIR__ . '/keys/public_key.pem',
            'algorithm' => $_ENV['JWT_ALGORITHM'], // Should be 'RS256'
            'access_token_expire' => (int)$_ENV['JWT_ACCESS_TOKEN_EXPIRE'],
            'refresh_token_expire' => (int)$_ENV['JWT_REFRESH_TOKEN_EXPIRE'],
        ],

        'cors' => [
            'allowed_origins' => \explode(',', $_ENV['CORS_ALLOWED_ORIGINS']),
            'allowed_methods' => \explode(',', $_ENV['CORS_ALLOWED_METHODS']),
            'allowed_headers' => \explode(',', $_ENV['CORS_ALLOWED_HEADERS']),
            'exposed_headers' => \explode(',', $_ENV['CORS_EXPOSED_HEADERS']),
            'allow_credentials' => $_ENV['CORS_ALLOW_CREDENTIALS'] === 'true',
            'max_age' => (int)$_ENV['CORS_MAX_AGE'],
        ],

        'rate_limit' => [
            'enabled' => $_ENV['RATE_LIMIT_ENABLED'] === 'true',
            'max_requests' => (int)$_ENV['RATE_LIMIT_MAX_REQUESTS'],
            'window' => (int)$_ENV['RATE_LIMIT_WINDOW'],
        ],

        'password_reset' => [
            'expire' => (int)$_ENV['PASSWORD_RESET_EXPIRE'],
            'url' => $_ENV['PASSWORD_RESET_URL'],
        ],

        'email_verification' => [
            'expire' => (int)$_ENV['EMAIL_VERIFICATION_EXPIRE'],
            'url' => $_ENV['EMAIL_VERIFICATION_URL'],
        ],

        'site_url' => $_ENV['SITE_URL'],
        'app_name' => $_ENV['APP_NAME'],

        'mail' => [
            'host' => $_ENV['MAIL_HOST'],
            'port' => (int)$_ENV['MAIL_PORT'],
            'username' => $_ENV['MAIL_USERNAME'],
            'password' => $_ENV['MAIL_PASSWORD'],
            'encryption' => $_ENV['MAIL_ENCRYPTION'],
            'from_email' => $_ENV['MAIL_FROM_EMAIL'],
            'from_name' => $_ENV['MAIL_FROM_NAME'],
        ],
    ],
];
