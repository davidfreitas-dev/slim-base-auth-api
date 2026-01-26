<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\VerifyEmailResponseDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VerifyEmailResponseDTO::class)]
class VerifyEmailResponseDTOTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $tokenData = ['access_token' => 'some_token'];
        $wasAlreadyVerified = true;

        $dto = new VerifyEmailResponseDTO($tokenData, $wasAlreadyVerified);

        $this->assertSame($tokenData, $dto->getTokenData());
        $this->assertSame($wasAlreadyVerified, $dto->wasAlreadyVerified());
    }
}
