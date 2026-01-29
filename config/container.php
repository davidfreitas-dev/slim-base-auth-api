<?php

declare(strict_types=1);

// PSR Interfaces
use App\Application\{
    Service\ErrorLoggerService,
    Service\ValidationService,
    UseCase\ChangePasswordUseCase,
    UseCase\CreateUserAdminUseCase,
    UseCase\DeleteUserUseCase,
    UseCase\GetErrorLogDetailsUseCase,
    UseCase\ListErrorLogsUseCase,
    UseCase\RegisterUserUseCase,
    UseCase\ResetPasswordUseCase,
    UseCase\ResolveErrorLogUseCase,
    UseCase\UpdateUserAdminUseCase,
    UseCase\UpdateUserProfileUseCase,
    UseCase\ValidateResetCodeUseCase,
    UseCase\VerifyEmailUseCase
};
use App\Domain\Repository\{
    ErrorLogRepositoryInterface,
    PasswordResetRepositoryInterface,
    PersonRepositoryInterface,
    RoleRepositoryInterface,
    UserRepositoryInterface,
    UserVerificationRepositoryInterface
};
use App\Infrastructure\{
    Http\Middleware\CorsMiddleware,
    Http\Middleware\ErrorMiddleware,
    Http\Middleware\RateLimitMiddleware,
    Http\Response\JsonResponseFactory,
    Logging\Monolog\DatabaseErrorLogHandler,
    Mailer\MailerInterface,
    Mailer\PHPMailerService,
    Persistence\Decorator\CachingUserRepository,
    Persistence\MySQL\DatabaseErrorLogRepository,
    Persistence\MySQL\PasswordResetRepository,
    Persistence\MySQL\PersonRepository,
    Persistence\MySQL\RoleRepository,
    Persistence\MySQL\UserRepository,
    Persistence\MySQL\UserVerificationRepository,
    Persistence\Redis\RedisCache,
    Security\JwtService,
    Security\PasswordHasher
};
// Domain Layer
use App\Presentation\Api\V1\Controller\ErrorLogController;
// Application Layer
// Infrastructure Layer
use Monolog\{Handler\StreamHandler, Level, Logger};
use Psr\{
    Container\ContainerInterface,
    Http\Message\ResponseFactoryInterface,
    Http\Message\ServerRequestFactoryInterface,
    Http\Message\StreamFactoryInterface,
    Http\Message\UploadedFileFactoryInterface,
    Http\Message\UriFactoryInterface,
    Log\LoggerInterface
};
// Presentation Layer
use Slim\Psr7\Factory\{
    ServerRequestFactory,
    StreamFactory,
    UploadedFileFactory,
    UriFactory
};
// Third Party
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return [
    // Database Connection
    PDO::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['db'];

        $dsn = \sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $settings['host'],
            $settings['port'],
            $settings['database'],
            $settings['charset'],
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $settings['username'], $settings['password'], $options);
    },

    // Redis Client (extensão nativa phpredis)
    \Redis::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['redis'];

        $redis = new \Redis();

        // Conectar ao Redis
        $redis->connect(
            $settings['host'],
            $settings['port'],
        );

        // Autenticar se houver senha
        if (!empty($settings['password'])) {
            $redis->auth($settings['password']);
        }

        // Selecionar database
        if (isset($settings['database'])) {
            $redis->select($settings['database']);
        }

        // Configurações opcionais (não serializar para usar nosso próprio serialize/unserialize)
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $redis;
    },

    // Redis Cache
    RedisCache::class => fn (ContainerInterface $c) => new RedisCache($c->get(\Redis::class)),

    // Logger
    LoggerInterface::class => function (ContainerInterface $c) {
        $logger = new Logger('api');
        // Stream handler for general logging (e.g., to file or stdout)
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Level::Warning)); // Use Debug to see INFO type

        // Database handler for critical errors (ERROR and CRITICAL levels)
        $logger->pushHandler(new DatabaseErrorLogHandler(
            $c->get(ErrorLoggerService::class),
            Level::Error->value, // Pass the integer value
        ));

        return $logger;
    },

    ErrorMiddleware::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new ErrorMiddleware(
            $c->get(LoggerInterface::class),
            $c->get(JsonResponseFactory::class),
            $settings['displayErrorDetails'],
        );
    },

    RateLimitMiddleware::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new RateLimitMiddleware(
            $c->get(RedisCache::class),
            $c->get(JwtService::class),
            $settings['rate_limit'],
        );
    },

    CorsMiddleware::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new CorsMiddleware($settings['cors']);
    },

    JsonResponseFactory::class => fn (ContainerInterface $c) => new JsonResponseFactory($c->get(ResponseFactoryInterface::class)),

    // Security Services
    JwtService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['jwt'];

        return new JwtService(
            $settings['private_key_path'],
            $settings['public_key_path'],
            $settings['algorithm'],
            $settings['access_token_expire'],
            $settings['refresh_token_expire'],
            $c->get(RedisCache::class),
            $c->get(UserRepositoryInterface::class),
        );
    },

    PasswordHasher::class => fn () => new PasswordHasher(),

    ErrorLoggerService::class => fn (ContainerInterface $c) => new App\Application\Service\ErrorLoggerService(
        $c->get(ErrorLogRepositoryInterface::class),
    ),

    // Repositories
    PersonRepositoryInterface::class => fn (ContainerInterface $c) => new PersonRepository($c->get(PDO::class)),

    RoleRepositoryInterface::class => fn (ContainerInterface $c) => new RoleRepository($c->get(PDO::class)),

    ErrorLogRepositoryInterface::class => fn (ContainerInterface $c) => new DatabaseErrorLogRepository($c->get(PDO::class)),

    // Caching decorator for UserRepository
    UserRepository::class => fn (ContainerInterface $c) => new UserRepository(
        $c->get(PDO::class),
        $c->get(PersonRepositoryInterface::class),
        $c->get(RoleRepositoryInterface::class),
    ),

    UserRepositoryInterface::class => fn (ContainerInterface $c) => new CachingUserRepository(
        $c->get(UserRepository::class),
        $c->get(RedisCache::class),
        $c->get(LoggerInterface::class),
    ),

    PasswordResetRepositoryInterface::class => fn (ContainerInterface $c) => new PasswordResetRepository($c->get(PDO::class)),

    UserVerificationRepositoryInterface::class => fn (ContainerInterface $c) => new UserVerificationRepository($c->get(PDO::class)),

    // Mailer Service
    MailerInterface::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new PHPMailerService(
            $c->get(LoggerInterface::class),
            $settings['mail']['host'],
            $settings['mail']['port'],
            $settings['mail']['username'],
            $settings['mail']['password'],
            $settings['mail']['encryption'],
            $settings['mail']['from_email'],
            $settings['mail']['from_name'],
            $settings['site_url'],
            $settings['app_name'],
        );
    },

    // Use Cases
    ListErrorLogsUseCase::class => fn (ContainerInterface $c) => new ListErrorLogsUseCase(
        $c->get(ErrorLogRepositoryInterface::class),
    ),

    GetErrorLogDetailsUseCase::class => fn (ContainerInterface $c) => new GetErrorLogDetailsUseCase(
        $c->get(ErrorLogRepositoryInterface::class),
    ),

    ResolveErrorLogUseCase::class => fn (ContainerInterface $c) => new ResolveErrorLogUseCase(
        $c->get(ErrorLogRepositoryInterface::class),
    ),

    UpdateUserProfileUseCase::class => fn (ContainerInterface $c) => new UpdateUserProfileUseCase(
        $c->get(PDO::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(PersonRepositoryInterface::class),
        $c->get('settings')['paths']['upload_path'],
    ),

    ChangePasswordUseCase::class => fn (ContainerInterface $c) => new ChangePasswordUseCase(
        $c->get(UserRepositoryInterface::class),
        $c->get(PasswordHasher::class),
        $c->get(MailerInterface::class),
        $c->get(JwtService::class),
    ),

    ResetPasswordUseCase::class => fn (ContainerInterface $c) => new ResetPasswordUseCase(
        $c->get(UserRepositoryInterface::class),
        $c->get(PasswordResetRepositoryInterface::class),
        $c->get(PasswordHasher::class),
        $c->get(JwtService::class),
    ),

    VerifyEmailUseCase::class => fn (ContainerInterface $c) => new VerifyEmailUseCase(
        $c->get(UserVerificationRepositoryInterface::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(JwtService::class),
    ),

    ValidateResetCodeUseCase::class => fn (ContainerInterface $c) => new ValidateResetCodeUseCase(
        $c->get(PasswordResetRepositoryInterface::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(LoggerInterface::class),
    ),

    RegisterUserUseCase::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $roleRepository = $c->get(RoleRepositoryInterface::class);
        $defaultUserRole = $roleRepository->findByName('customer');

        if (!$defaultUserRole) {
            throw new \RuntimeException("Default 'customer' role not found in the database.");
        }

        return new RegisterUserUseCase(
            $c->get(PDO::class),
            $c->get(PersonRepositoryInterface::class),
            $c->get(UserRepositoryInterface::class),
            $c->get(UserVerificationRepositoryInterface::class),
            $c->get(PasswordHasher::class),
            $c->get(MailerInterface::class),
            $settings['email_verification']['url'],
            $settings['email_verification']['expire'],
            $defaultUserRole,
        );
    },

    DeleteUserUseCase::class => fn (ContainerInterface $c) => new DeleteUserUseCase(
        $c->get(UserRepositoryInterface::class),
        $c->get(PersonRepositoryInterface::class),
        $c->get(JwtService::class),
        $c->get(PDO::class),
    ),

    CreateUserAdminUseCase::class => function (ContainerInterface $c) {
        $roleRepository = $c->get(RoleRepositoryInterface::class);
        $defaultUserRole = $roleRepository->findByName('admin');

        if (!$defaultUserRole) {
            throw new \RuntimeException("Default 'admin' role not found in the database.");
        }

        return new CreateUserAdminUseCase(
            $c->get(PDO::class),
            $c->get(PersonRepositoryInterface::class),
            $c->get(UserRepositoryInterface::class),
            $c->get(RoleRepositoryInterface::class),
            $c->get(PasswordHasher::class),
        );
    },

    UpdateUserAdminUseCase::class => fn (ContainerInterface $c) => new UpdateUserAdminUseCase(
        $c->get(PDO::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(PersonRepositoryInterface::class),
        $c->get(RoleRepositoryInterface::class),
    ),

    // Controllers
    ErrorLogController::class => fn (ContainerInterface $c) => new ErrorLogController(
        $c->get(JsonResponseFactory::class),
        $c->get(ListErrorLogsUseCase::class),
        $c->get(GetErrorLogDetailsUseCase::class),
        $c->get(ResolveErrorLogUseCase::class),
        $c->get(LoggerInterface::class),
    ),

    // Symfony Validator
    ValidatorInterface::class => fn () => Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator(),

    ValidationService::class => fn (ContainerInterface $c) => new ValidationService($c->get(ValidatorInterface::class)),

    // PSR-7 Factories
    ServerRequestFactoryInterface::class => fn () => new ServerRequestFactory(),

    StreamFactoryInterface::class => fn () => new StreamFactory(),

    UriFactoryInterface::class => fn () => new UriFactory(),

    UploadedFileFactoryInterface::class => fn () => new UploadedFileFactory(),
];
