<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use App\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class NotFoundExceptionTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $exception = new NotFoundException();
        $this->assertEquals(404, $exception->getStatusCode());
    }
}
