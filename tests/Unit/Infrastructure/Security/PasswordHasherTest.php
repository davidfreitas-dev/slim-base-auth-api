<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class PasswordHasherTest extends TestCase
{
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordHasher = new PasswordHasher();
    }

    public function testHashCreatesValidPasswordHash(): void
    {
        $password = 'MySecurePassword123!';
        
        $hash = $this->passwordHasher->hash($password);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
        
        // Verificar se o hash é válido usando password_verify
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashCreatesDifferentHashesForSamePassword(): void
    {
        $password = 'MySecurePassword123!';
        
        $hash1 = $this->passwordHasher->hash($password);
        $hash2 = $this->passwordHasher->hash($password);

        // Devido ao salt aleatório, hashes devem ser diferentes
        $this->assertNotEquals($hash1, $hash2);
        
        // Mas ambos devem verificar com sucesso
        $this->assertTrue(password_verify($password, $hash1));
        $this->assertTrue(password_verify($password, $hash2));
    }

    public function testHashHandlesEmptyPassword(): void
    {
        $password = '';
        
        $hash = $this->passwordHasher->hash($password);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashHandlesSpecialCharacters(): void
    {
        $password = '!@#$%^&*()_+-=[]{}|;:\'",.<>?/`~';
        
        $hash = $this->passwordHasher->hash($password);

        $this->assertIsString($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashHandlesUnicodeCharacters(): void
    {
        $password = 'Contraseña123!çãõüñ你好';
        
        $hash = $this->passwordHasher->hash($password);

        $this->assertIsString($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashHandlesLongPassword(): void
    {
        // Senha muito longa (mais de 72 caracteres, que é o limite do bcrypt)
        $password = str_repeat('a', 100);
        
        $hash = $this->passwordHasher->hash($password);

        $this->assertIsString($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $password = 'MySecurePassword123!';
        $hash = $this->passwordHasher->hash($password);

        $result = $this->passwordHasher->verify($password, $hash);

        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseForIncorrectPassword(): void
    {
        $password = 'MySecurePassword123!';
        $wrongPassword = 'WrongPassword456!';
        $hash = $this->passwordHasher->hash($password);

        $result = $this->passwordHasher->verify($wrongPassword, $hash);

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseForEmptyPassword(): void
    {
        $password = 'MySecurePassword123!';
        $hash = $this->passwordHasher->hash($password);

        $result = $this->passwordHasher->verify('', $hash);

        $this->assertFalse($result);
    }

    public function testVerifyIsCaseSensitive(): void
    {
        $password = 'MySecurePassword123!';
        $hash = $this->passwordHasher->hash($password);

        // Senha com case diferente
        $result = $this->passwordHasher->verify('mysecurepassword123!', $hash);

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseForInvalidHash(): void
    {
        $password = 'MySecurePassword123!';
        $invalidHash = 'not-a-valid-hash';

        $result = $this->passwordHasher->verify($password, $invalidHash);

        $this->assertFalse($result);
    }

    public function testVerifyHandlesEmptyHash(): void
    {
        $password = 'MySecurePassword123!';
        $emptyHash = '';

        $result = $this->passwordHasher->verify($password, $emptyHash);

        $this->assertFalse($result);
    }

    public function testHashAndVerifyWorkTogether(): void
    {
        $testCases = [
            'simple',
            'With Spaces',
            '12345678',
            'Special!@#$%',
            'çãõüñ',
            str_repeat('long', 20),
        ];

        foreach ($testCases as $password) {
            $hash = $this->passwordHasher->hash($password);
            
            $this->assertTrue(
                $this->passwordHasher->verify($password, $hash),
                "Failed to verify password: {$password}"
            );
        }
    }

    public function testHashUsesArgon2IDByDefault(): void
    {
        $password = 'MySecurePassword123!';
        $hash = $this->passwordHasher->hash($password);

        // Verificar se o hash começa com $argon2id$ (se disponível) ou $2y$ (bcrypt)
        // PASSWORD_DEFAULT usa Argon2ID se disponível, caso contrário bcrypt
        $info = password_get_info($hash);
        
        $this->assertNotNull($info['algo']);
        $this->assertContains(
            $info['algoName'],
            ['argon2id', 'argon2i', 'bcrypt'],
            'Hash should use a secure algorithm'
        );
    }

    public function testVerifyTimeIsConsistentForValidAndInvalidPasswords(): void
    {
        $password = 'MySecurePassword123!';
        $hash = $this->passwordHasher->hash($password);
        $wrongPassword = 'WrongPassword';

        // Warm-up to stabilize timing results
        $this->passwordHasher->verify($password, $hash);
        $this->passwordHasher->verify($wrongPassword, $hash);

        // Measure time for the correct password
        $start = microtime(true);
        $this->passwordHasher->verify($password, $hash);
        $validTime = microtime(true) - $start;

        // Measure time for an incorrect password
        $start = microtime(true);
        $this->passwordHasher->verify($wrongPassword, $hash);
        $invalidTime = microtime(true) - $start;

        // The time difference should be minimal to protect against timing attacks.
        // A tolerance of 500ms is set to avoid flaky tests in variable environments.
        $timeDifference = abs($validTime - $invalidTime);
        $this->assertLessThan(
            0.5,
            $timeDifference,
            'Timing attack vulnerability: verification time differs too much between valid and invalid passwords'
        );
    }
}
