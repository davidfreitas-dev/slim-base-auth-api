<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Domain\Entity\User;
use App\Domain\Enum\JwtTokenType;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Redis\RedisCache;
use App\Infrastructure\Security\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;
    private UserRepositoryInterface|MockObject $userRepository;
    private RedisCache|MockObject $cache;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $algorithm = 'RS256';
    private int $accessTokenExpire = 900; // 15 minutos
    private int $refreshTokenExpire = 604800; // 7 dias

    protected function setUp(): void
    {
        parent::setUp();

        // Criar chaves temporárias para teste
        $this->createTemporaryKeys();

        // Mock das dependências
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->cache = $this->createMock(RedisCache::class);

        // Instanciar o serviço
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
        parent::tearDown();
        
        // Remover chaves temporárias
        if (file_exists($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        if (file_exists($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }
    }

    private function createTemporaryKeys(): void
    {
        $tempDir = sys_get_temp_dir();
        $this->privateKeyPath = $tempDir . '/test_private_key.pem';
        $this->publicKeyPath = $tempDir . '/test_public_key.pem';

        // Gerar par de chaves RSA para teste
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

    private function createMockUser(int $id, string $email, string $role = 'user', bool $isVerified = true): User|MockObject
    {
        $user = $this->createMock(User::class);
        
        $roleMock = $this->createMock(\App\Domain\Entity\Role::class);
        $roleMock->method('getName')->willReturn($role);
        
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        $user->method('getRole')->willReturn($roleMock);
        $user->method('isVerified')->willReturn($isVerified);

        return $user;
    }

    public function testGenerateAccessTokenSuccessfully(): void
    {
        $userId = 1;
        $email = 'test@example.com';
        $user = $this->createMockUser($userId, $email, 'user', true);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $_ENV['APP_URL'] = 'http://localhost:8000';

        $token = $this->jwtService->generateAccessToken($userId, $email);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Decodificar e validar o payload
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals($email, $decoded->email);
        $this->assertEquals('user', $decoded->role);
        $this->assertTrue($decoded->is_verified);
        $this->assertEquals(JwtTokenType::ACCESS->value, $decoded->type);
        $this->assertEquals($_ENV['APP_URL'], $decoded->iss);
        $this->assertEquals($_ENV['APP_URL'], $decoded->aud);
        $this->assertNotEmpty($decoded->jti);
    }

    public function testGenerateAccessTokenThrowsExceptionWhenUserNotFound(): void
    {
        $userId = 999;
        $email = 'notfound@example.com';

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User not found for JWT generation');

        $this->jwtService->generateAccessToken($userId, $email);
    }

    public function testGenerateRefreshTokenSuccessfully(): void
    {
        $userId = 1;
        $user = $this->createMockUser($userId, 'test@example.com');

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        // Espera que o token seja armazenado no cache
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->matchesRegularExpression('/^refresh_token:/'),
                $userId,
                $this->refreshTokenExpire
            );

        $this->cache
            ->expects($this->once())
            ->method('sAdd')
            ->with(
                'user_refresh_tokens:' . $userId,
                $this->matchesRegularExpression('/^[0-9a-f-]{36}$/')
            );

        $_ENV['APP_URL'] = 'http://localhost:8000';

        $token = $this->jwtService->generateRefreshToken($userId);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Validar payload
        $publicKey = file_get_contents($this->publicKeyPath);
        $decoded = JWT::decode($token, new Key($publicKey, $this->algorithm));

        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals('user', $decoded->role);
        $this->assertEquals(JwtTokenType::REFRESH->value, $decoded->type);
    }

    public function testGenerateRefreshTokenThrowsExceptionWhenUserNotFound(): void
    {
        $userId = 999;

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User not found for JWT generation');

        $this->jwtService->generateRefreshToken($userId);
    }

    public function testValidateTokenSuccessfully(): void
    {
        $userId = 1;
        $email = 'test@example.com';
        $user = $this->createMockUser($userId, $email);

        $this->userRepository->method('findById')->willReturn($user);
        $_ENV['APP_URL'] = 'http://localhost:8000';

        $token = $this->jwtService->generateAccessToken($userId, $email);

        // Mock do cache para indicar que o token não está bloqueado
        $this->cache
            ->expects($this->once())
            ->method('has')
            ->willReturn(false);

        $decoded = $this->jwtService->validateToken($token);

        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals($email, $decoded->email);
    }

    public function testBlockAndCheckTokenWorkflow(): void
    {
        $jti = 'workflow-test-jti';
        $expiresAt = time() + 3600;

        // Expectativa de bloqueio
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                'blocked_token:' . $jti,
                1,
                $this->greaterThan(0)
            );

        // Bloquear o token
        $this->jwtService->blockToken($jti, $expiresAt);

        // Configurar expectativa para checagem
        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('blocked_token:' . $jti)
            ->willReturn(true);

        // Verificar se está bloqueado
        $this->assertTrue($this->jwtService->isTokenBlocked($jti));
    }

    public function testValidateTokenThrowsExceptionForInvalidToken(): void
    {
        $invalidToken = 'invalid.token.here';

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $this->jwtService->validateToken($invalidToken);
    }

    public function testBlockToken(): void
    {
        $jti = 'test-jti-123';
        $expiresAt = time() + 3600;

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                'blocked_token:' . $jti,
                1,
                $this->greaterThan(0)
            );

        $this->jwtService->blockToken($jti, $expiresAt);
    }

    public function testBlockTokenDoesNothingWhenAlreadyExpired(): void
    {
        $jti = 'test-jti-123';
        $expiresAt = time() - 3600; // Já expirado

        $this->cache
            ->expects($this->never())
            ->method('set');

        $this->jwtService->blockToken($jti, $expiresAt);
    }

    public function testIsTokenBlockedReturnsTrue(): void
    {
        $jti = 'test-jti-123';

        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('blocked_token:' . $jti)
            ->willReturn(true);

        $result = $this->jwtService->isTokenBlocked($jti);

        $this->assertTrue($result);
    }

    public function testIsTokenBlockedReturnsFalse(): void
    {
        $jti = 'test-jti-456';

        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('blocked_token:' . $jti)
            ->willReturn(false);

        $result = $this->jwtService->isTokenBlocked($jti);

        $this->assertFalse($result);
    }

    public function testRevokeRefreshToken(): void
    {
        $jti = 'test-jti-123';
        $userId = 1;

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('refresh_token:' . $jti)
            ->willReturn($userId);

        $this->cache
            ->expects($this->once())
            ->method('sRem')
            ->with('user_refresh_tokens:' . $userId, $jti);

        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('refresh_token:' . $jti);

        $this->jwtService->revokeRefreshToken($jti);
    }

    public function testInvalidateAllUserRefreshTokens(): void
    {
        $userId = 1;
        $jtis = ['jti-1', 'jti-2', 'jti-3'];

        $this->cache
            ->expects($this->once())
            ->method('sMembers')
            ->with('user_refresh_tokens:' . $userId)
            ->willReturn($jtis);

        // Expectativa para as 4 chamadas de delete
        $deleteCallCount = 0;
        $this->cache
            ->expects($this->exactly(4))
            ->method('delete')
            ->willReturnCallback(function($key) use (&$deleteCallCount, $jtis, $userId) {
                $deleteCallCount++;
                
                if ($deleteCallCount <= 3) {
                    $expectedKey = 'refresh_token:jti-' . $deleteCallCount;
                    $this->assertEquals($expectedKey, $key);
                } else {
                    $this->assertEquals('user_refresh_tokens:' . $userId, $key);
                }
                
                return true;
            });

        $this->jwtService->invalidateAllUserRefreshTokens($userId);
    }

    public function testIsRefreshTokenValid(): void
    {
        $jti = 'test-jti-123';

        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('refresh_token:' . $jti)
            ->willReturn(true);

        $result = $this->jwtService->isRefreshTokenValid($jti);

        $this->assertTrue($result);
    }

    public function testGetAccessTokenExpire(): void
    {
        $this->assertEquals($this->accessTokenExpire, $this->jwtService->getAccessTokenExpire());
    }

    public function testGetRefreshTokenExpire(): void
    {
        $this->assertEquals($this->refreshTokenExpire, $this->jwtService->getRefreshTokenExpire());
    }
}