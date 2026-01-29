<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enum;

use App\Domain\Enum\JwtTokenType;
use PHPUnit\Framework\TestCase;

final class JwtTokenTypeTest extends TestCase
{
    public function testEnumCasesHaveCorrectValues(): void
    {
        $this->assertSame('access', JwtTokenType::ACCESS->value);
        $this->assertSame('refresh', JwtTokenType::REFRESH->value);
    }
}
