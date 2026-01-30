<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use App\Domain\Exception\AuthorizationException;
use PHPUnit\Framework\TestCase;

class AuthorizationExceptionTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $exception = new AuthorizationException();
        $this->assertEquals(403, $exception->getStatusCode());
    }
}
