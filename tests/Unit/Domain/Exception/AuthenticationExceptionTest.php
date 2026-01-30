<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use App\Domain\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $exception = new AuthenticationException();
        $this->assertEquals(401, $exception->getStatusCode());
    }
}
