<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use App\Domain\Exception\ConflictException;
use PHPUnit\Framework\TestCase;

class ConflictExceptionTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $exception = new ConflictException();
        $this->assertEquals(409, $exception->getStatusCode());
    }
}
