<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enum;

use App\Domain\Enum\JsonResponseKey;
use PHPUnit\Framework\TestCase;

final class JsonResponseKeyTest extends TestCase
{
    public function testEnumCasesHaveCorrectValues(): void
    {
        $this->assertSame('status', JsonResponseKey::STATUS->value);
        $this->assertSame('message', JsonResponseKey::MESSAGE->value);
        $this->assertSame('data', JsonResponseKey::DATA->value);
        $this->assertSame('errors', JsonResponseKey::ERRORS->value);
        $this->assertSame('access_token', JsonResponseKey::ACCESS_TOKEN->value);
        $this->assertSame('refresh_token', JsonResponseKey::REFRESH_TOKEN->value);
        $this->assertSame('token_type', JsonResponseKey::TOKEN_TYPE->value);
        $this->assertSame('expires_in', JsonResponseKey::EXPIRES_IN->value);
        $this->assertSame('debug', JsonResponseKey::DEBUG->value);
        $this->assertSame('file', JsonResponseKey::FILE->value);
        $this->assertSame('line', JsonResponseKey::LINE->value);
        $this->assertSame('trace', JsonResponseKey::TRACE->value);
    }
}
