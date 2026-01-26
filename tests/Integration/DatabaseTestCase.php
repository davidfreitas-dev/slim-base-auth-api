<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Redis;

abstract class DatabaseTestCase extends TestCase
{
    protected static ?PDO $pdo = null;
    protected static ?Redis $redis = null;

    public static function setUpBeforeClass(): void
    {
        self::ensureTestEnvironment();

        if (!self::$pdo instanceof \PDO) {
            $db = [
                'host' => $_ENV['DB_TEST_HOST'],
                'port' => (int)$_ENV['DB_TEST_PORT'],
                'database' => $_ENV['DB_TEST_NAME'],
                'username' => $_ENV['DB_TEST_USER'],
                'password' => $_ENV['DB_TEST_PASS'],
                'charset' => $_ENV['DB_TEST_CHARSET'],
            ];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );
            
            self::$pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_AUTOCOMMIT => 1,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=60, interactive_timeout=60",
            ]);
        }

        if (self::$redis === null) {
            self::$redis = self::createRedisConnection();
        }
    }

    private static function ensureTestEnvironment(): void
    {
        $testDatabaseName = $_ENV['DB_TEST_NAME'] ?? '';
        $testHost = $_ENV['DB_TEST_HOST'] ?? '';

        if (!str_contains($testDatabaseName, 'test') && $testHost !== 'database_test') {
            throw new \RuntimeException(
                'ATENÇÃO: Os testes não estão configurados para usar o banco de testes! ' .
                'Banco atual: ' . $testDatabaseName . ' no host: ' . $testHost . '. ' .
                'Verifique o phpunit.xml'
            );
        }

        if ($testHost !== 'database_test') {
            throw new \RuntimeException(
                'ATENÇÃO: DB_TEST_HOST deveria ser "database_test" mas está configurado como: ' . $testHost
            );
        }
    }

    private static function createRedisConnection(): ?Redis
    {
        try {
            if (!extension_loaded('redis')) {
                return null;
            }

            $redis = new Redis();
            
            $connected = $redis->connect(
                $_ENV['REDIS_HOST'] ?? 'redis',
                (int)($_ENV['REDIS_PORT'] ?? 6379),
                2.0
            );

            if (!$connected) {
                return null;
            }

            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $redis->auth($_ENV['REDIS_PASSWORD']);
            }

            if (!empty($_ENV['REDIS_DATABASE'])) {
                $redis->select((int)$_ENV['REDIS_DATABASE']);
            }

            return $redis;
        } catch (\Exception) {
            return null;
        }
    }

    protected function setUp(): void
    {
        $this->cleanDatabase();
        self::$pdo->beginTransaction(); // Start a transaction for each test to isolate changes
        $this->cleanCache();
    }

    protected function tearDown(): void
    {
        // Rollback the transaction to undo changes made by the test
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        $this->cleanCache();
    }

    /**
     * Garante que não há transação ativa que possa causar locks
     */
    private function ensureNoActiveTransaction(): void
    {
        try {
            if (self::$pdo && self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
        } catch (\Exception) {
            // Ignora erros se não houver transação
        }
    }

    protected function cleanDatabase(): void
    {
        $maxRetries = 3;
        $retry = 0;
        
        while ($retry < $maxRetries) {
            try {
                $this->executeCleanDatabase();
                return; // Sucesso, sai do método
            } catch (\PDOException $e) {
                $retry++;
                
                // Se for lock/timeout, tenta novamente
                if ($retry < $maxRetries && $this->isLockError($e)) {
                    usleep(100000); // Aguarda 100ms antes de tentar novamente
                    continue;
                }
                
                // Se não for erro de lock ou esgotou tentativas, lança exceção
                error_log("Erro ao limpar banco (tentativa {$retry}/{$maxRetries}): " . $e->getMessage());
                throw $e;
            }
        }
    }

    private function isLockError(\PDOException $e): bool
    {
        $lockErrorCodes = [
            1205, // Lock wait timeout
            1213, // Deadlock
            1317, // Query interrupted
        ];
        
        foreach ($lockErrorCodes as $code) {
            if (str_contains($e->getMessage(), (string)$code)) {
                return true;
            }
        }
        
        return false;
    }

    private function executeCleanDatabase(): void
    {
        // Força rollback de qualquer transação pendente
        $this->ensureNoActiveTransaction();
        
        // Desabilita foreign keys
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        try {
            // Trunca tabelas na ordem correta (dependentes primeiro)
            $tables = [
                'password_resets',
                'user_verifications',
                'error_logs',
                'users',
                'persons',
                'roles',
            ];
            
            foreach ($tables as $table) {
                self::$pdo->exec("TRUNCATE TABLE {$table}");
            }

            // Reset auto-increment
            self::$pdo->exec('ALTER TABLE persons AUTO_INCREMENT = 1');
            self::$pdo->exec('ALTER TABLE users AUTO_INCREMENT = 1');
            self::$pdo->exec('ALTER TABLE roles AUTO_INCREMENT = 1');

            // Re-seed roles em uma única query
            self::$pdo->exec("
                INSERT INTO roles (name, description) VALUES 
                ('customer', 'Cliente final que utiliza os serviços do sistema'),
                ('user', 'Funcionário que presta atendimento aos clientes'),
                ('admin', 'Administrador com acesso total ao sistema')
            ");
            
        } finally {
            // SEMPRE reabilita foreign keys, mesmo em caso de erro
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected function cleanCache(): void
    {
        if (self::$redis instanceof Redis) {
            try {
                self::$redis->flushDB();
            } catch (\Exception) {
                // Silenciosamente ignora erros
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo instanceof \PDO) {
            try {
                // Garante que não há transação
                if (self::$pdo->inTransaction()) {
                    self::$pdo->rollBack();
                }
                
                self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                
                $tables = [
                    'password_resets',
                    'user_verifications',
                    'error_logs',
                    'users',
                    'persons',
                    'roles',
                ];
                
                foreach ($tables as $table) {
                    self::$pdo->exec("TRUNCATE TABLE {$table}");
                }
                
                self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Exception $e) {
                // Garante que foreign keys sejam restauradas
                try {
                    self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                } catch (\Exception) {
                    // Ignora
                }
                
                error_log("Erro no tearDownAfterClass: " . $e->getMessage());
            }

            self::$pdo = null;
        }

        if (self::$redis instanceof Redis) {
            try {
                self::$redis->flushDB();
                self::$redis->close();
            } catch (\Exception) {
                // Ignora
            }

            self::$redis = null;
        }
    }
}