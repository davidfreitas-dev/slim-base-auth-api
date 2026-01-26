<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\ChangePasswordRequestDTO;
use Tests\TestCase;

class ChangePasswordRequestDTOTest extends TestCase
{
    public function testFromArrayWithValidData(): void
    {
        $data = [
            'current_password' => 'old_password',
            'new_password' => 'new_password',
            'new_password_confirm' => 'new_password',
        ];
        $userId = 1;

        $dto = ChangePasswordRequestDTO::fromArray($data, $userId);

        $this->assertSame($userId, $dto->userId);
        $this->assertSame($data['current_password'], $dto->currentPassword);
        $this->assertSame($data['new_password'], $dto->newPassword);
        $this->assertSame($data['new_password_confirm'], $dto->newPasswordConfirm);
    }
}
