<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Security;

use Faker\Factory;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Domain\Enum\JwtTokenType;
use App\Domain\ValueObject\CpfCnpj;
use Tests\Integration\DatabaseTestCase;
use App\Infrastructure\Security\JwtService;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Persistence\Redis\RedisCache;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use App\Infrastructure\Persistence\MySQL\UserRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;

class JwtServiceTest extends DatabaseTestCase
{
    private JwtService $jwtService;
    private UserRepository $userRepository;
    private PersonRepository $personRepository;
    private RoleRepository $roleRepository;
    private RedisCache $cache;
    private \Faker\Generator $faker;
    
    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $algorithm = 'RS256';
    private int $accessTokenExpire = 900;
    private int $refreshTokenExpire = 604800;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Inicializar repositórios
        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo, $this->personRepository, $this->roleRepository);
        
        // Inicializar cache
        $this->cache = new RedisCache(self::$redis);
        
        // Inicializar Faker
        $this->faker = Factory::create('pt_BR');

        // Criar chaves temporárias para teste
        $this->createTemporaryKeys();

        // Instanciar JwtService com dependências reais
        $this->jwtService = new JwtService(
            $this->privateKeyPath,
            $this->publicKeyPath,
            $this->algorithm,
            $this->accessTokenExpire,
            $this->refreshTokenExpire,
            $this->cache,
            $this->userRepository
        );
    }

    protected function tearDown(): void
    {
        // Remover chaves temporárias
        if (file_exists($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        if (file_exists($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }

        parent::tearDown();
    }

    private function createTemporaryKeys(): void
    {
        $tempDir = sys_get_temp_dir();
        $this->privateKeyPath = $tempDir . '/integration_test_private_key_' . uniqid() . '.pem';
        $this->publicKeyPath = $tempDir . '/integration_test_public_key_' . uniqid() . '.pem';

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res);

        file_put_contents($this->privateKeyPath, $privateKey);
        file_put_contents($this->publicKeyPath, $publicKey['key']);
    }

    private function createTestUser(int $roleId = 2): User
    {
        $person = new Person(
            name: $this->faker->name,
            email: $this->faker->unique()->email,
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $this->personRepository->create($person);

        $role = $this->roleRepository->findById($roleId);
        if (!$role instanceof Role) {
            throw new NotFoundException(sprintf("Role with ID %d not found. Make sure it is seeded.", $roleId));
        }

        $user = new User(
            person: $person,
            password: 'password123',
            role: $role
        );
        
        return $this->userRepository->create($user);
    }

    public function testGenerateAndValidateAccessTokenWithRealDatabase(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar token
        $token = $this->jwtService->generateAccessToken(
            $user->getId(),
            $user->getEmail()
        );

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Validar token
        $decoded = $this->jwtService->validateToken($token);

        $this->assertEquals($user->getId(), $decoded->sub);
        $this->assertEquals($user->getEmail(), $decoded->email);
        $this->assertEquals($user->getRole()->getName(), $decoded->role);
        $this->assertEquals(JwtTokenType::ACCESS->value, $decoded->type);
    }

    public function testGenerateRefreshTokenAndStoreInRedis(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar refresh token
        $token = $this->jwtService->generateRefreshToken($user->getId());

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Decodificar token para obter o JTI
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        // Verificar se foi armazenado no Redis
        $this->assertTrue(
            $this->jwtService->isRefreshTokenValid($decoded->jti),
            'Refresh token should be stored in Redis'
        );

        // Verificar se foi adicionado ao set de tokens do usuário
        $userTokens = self::$redis->sMembers('user_refresh_tokens:' . $user->getId());
        $this->assertContains($decoded->jti, $userTokens);
    }

    public function testBlockToken(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar token
        $token = $this->jwtService->generateAccessToken(
            $user->getId(),
            $user->getEmail()
        );

        // Decodificar para obter JTI e exp
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        // Verificar que o token NÃO está bloqueado inicialmente
        $this->assertFalse($this->jwtService->isTokenBlocked($decoded->jti));

        // Bloquear o token
        $this->jwtService->blockToken($decoded->jti, $decoded->exp);

        // Verificar que agora está bloqueado
        $this->assertTrue($this->jwtService->isTokenBlocked($decoded->jti));
        
        // Verificar no Redis diretamente
        $exists = self::$redis->exists('blocked_token:' . $decoded->jti);
        $this->assertEquals(1, $exists);
    }

    public function testValidateBlockedTokenThrowsException(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar token
        $token = $this->jwtService->generateAccessToken(
            $user->getId(),
            $user->getEmail()
        );

        // Validar que funciona antes de bloquear
        $validatedToken = $this->jwtService->validateToken($token);
        $this->assertEquals($user->getId(), $validatedToken->sub);

        // Decodificar para obter JTI
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        // Bloquear diretamente no Redis para garantir que está bloqueado
        self::$redis->setex('blocked_token:' . $decoded->jti, 3600, '1');

        // Verificar que está bloqueado
        $this->assertTrue($this->jwtService->isTokenBlocked($decoded->jti));

        // Tentar validar deve lançar exceção
        $this->expectException(AuthenticationException::class);
        $this->jwtService->validateToken($token);
    }

    public function testRevokeRefreshTokenFromRedis(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar refresh token
        $token = $this->jwtService->generateRefreshToken($user->getId());

        // Decodificar para obter JTI
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        // Verificar que está válido
        $this->assertTrue($this->jwtService->isRefreshTokenValid($decoded->jti));

        // Revogar token
        $this->jwtService->revokeRefreshToken($decoded->jti);

        // Verificar que não está mais válido
        $this->assertFalse($this->jwtService->isRefreshTokenValid($decoded->jti));

        // Verificar que foi removido do set de tokens do usuário
        $userTokens = self::$redis->sMembers('user_refresh_tokens:' . $user->getId());
        $this->assertNotContains($decoded->jti, $userTokens);
    }

    public function testInvalidateAllUserRefreshTokensFromRedis(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Gerar múltiplos refresh tokens
        $jtis = [];
        for ($i = 0; $i < 3; $i++) {
            $token = $this->jwtService->generateRefreshToken($user->getId());
            
            $publicKey = file_get_contents($this->publicKeyPath);
            $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));
            $jtis[] = $decoded->jti;
        }

        // Verificar que todos estão válidos
        foreach ($jtis as $jti) {
            $this->assertTrue(
                $this->jwtService->isRefreshTokenValid($jti),
                "Token {$jti} should be valid before invalidation"
            );
        }

        // Invalidar todos os tokens do usuário
        $this->jwtService->invalidateAllUserRefreshTokens($user->getId());

        // Verificar que nenhum está mais válido
        foreach ($jtis as $jti) {
            $this->assertFalse(
                $this->jwtService->isRefreshTokenValid($jti),
                "Token {$jti} should be invalid after invalidation"
            );
        }

        // Verificar que o set de tokens do usuário foi removido
        $userTokens = self::$redis->sMembers('user_refresh_tokens:' . $user->getId());
        $this->assertEmpty($userTokens);
    }

    public function testRefreshTokenWorkflowCompleto(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // 1. Gerar access e refresh token
        $accessToken = $this->jwtService->generateAccessToken(
            $user->getId(),
            $user->getEmail()
        );
        $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

        // 2. Validar access token
        $accessDecoded = $this->jwtService->validateToken($accessToken);
        $this->assertEquals($user->getId(), $accessDecoded->sub);

        // 3. Validar refresh token
        $publicKey = file_get_contents($this->publicKeyPath);
        $refreshDecoded = JWT::decode($refreshToken, new Key($publicKey, $this->algorithm));
        $this->assertTrue($this->jwtService->isRefreshTokenValid($refreshDecoded->jti));

        // 4. Simular uso do refresh token: revogar o antigo e gerar novo
        $this->jwtService->revokeRefreshToken($refreshDecoded->jti);
        $newRefreshToken = $this->jwtService->generateRefreshToken($user->getId());

        // 5. Verificar que o antigo foi revogado
        $this->assertFalse($this->jwtService->isRefreshTokenValid($refreshDecoded->jti));

        // 6. Verificar que o novo está válido
        $newRefreshDecoded = JWT::decode($newRefreshToken, new Key($publicKey, $this->algorithm));
        $this->assertTrue($this->jwtService->isRefreshTokenValid($newRefreshDecoded->jti));
    }

    public function testExpiredTokenValidation(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user = $this->createTestUser();

        // Criar JwtService com tempo de expiração muito curto
        $shortExpiryJwtService = new JwtService(
            $this->privateKeyPath,
            $this->publicKeyPath,
            $this->algorithm,
            1, // 1 segundo
            1, // 1 segundo
            $this->cache,
            $this->userRepository
        );

        // Gerar token
        $token = $shortExpiryJwtService->generateAccessToken(
            $user->getId(),
            $user->getEmail()
        );

        // Aguardar expiração
        sleep(2);

        // Tentar validar token expirado
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token has expired');
        $this->jwtService->validateToken($token);
    }

    public function testMultipleUsersRefreshTokensAreIsolated(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8000';
        
        $user1 = $this->createTestUser();
        $user2 = $this->createTestUser();

        // Gerar tokens para ambos os usuários
        $token1 = $this->jwtService->generateRefreshToken($user1->getId());
        $token2 = $this->jwtService->generateRefreshToken($user2->getId());

        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded1 = JWT::decode($token1, new Key($publicKey, $this->algorithm));
        $decoded2 = JWT::decode($token2, new Key($publicKey, $this->algorithm));

        // Invalidar tokens do primeiro usuário
        $this->jwtService->invalidateAllUserRefreshTokens($user1->getId());

        // Verificar que apenas os tokens do primeiro usuário foram invalidados
        $this->assertFalse($this->jwtService->isRefreshTokenValid($decoded1->jti));
        $this->assertTrue($this->jwtService->isRefreshTokenValid($decoded2->jti));
    }

    public function testGetAccessTokenExpire(): void
    {
        $expire = $this->jwtService->getAccessTokenExpire();
        
        $this->assertEquals($this->accessTokenExpire, $expire);
    }

    public function testGetRefreshTokenExpire(): void
    {
        $expire = $this->jwtService->getRefreshTokenExpire();
        
        $this->assertEquals($this->refreshTokenExpire, $expire);
    }

    public function testBlockTokenDoesNothingWhenAlreadyExpired(): void
    {
        $jti = 'expired-token-jti';
        $expiresAt = time() - 3600; // Já expirado há 1 hora

        // Bloquear token expirado não deve fazer nada
        $this->jwtService->blockToken($jti, $expiresAt);

        // Verificar que não foi armazenado no Redis
        $this->assertFalse($this->jwtService->isTokenBlocked($jti));
    }
}