<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enum;

use App\Domain\Enum\JsonResponseStatus;
use PHPUnit\Framework\TestCase;

final class JsonResponseStatusTest extends TestCase
{
    public function testEnumCasesHaveCorrectValues(): void
    {
        $this->assertSame('success', JsonResponseStatus::SUCCESS->value);
        $this->assertSame('fail', JsonResponseStatus::FAIL->value);
        $this->assertSame('error', JsonResponseStatus::ERROR->value);
    }
}
