<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\UserProfileResponseDTO;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\ValueObject\CpfCnpj;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserProfileResponseDTO::class)]
class UserProfileResponseDTOTest extends TestCase
{
    public function testFromEntity(): void
    {
        $user = $this->createMock(User::class);
        $person = $this->createMock(Person::class);
        $role = $this->createMock(Role::class);

        $now = new DateTimeImmutable();
        $user->method('getId')->willReturn(1);
        $user->method('getPerson')->willReturn($person);
        $user->method('getRole')->willReturn($role);
        $user->method('isActive')->willReturn(true);
        $user->method('isVerified')->willReturn(true);
        $user->method('getCreatedAt')->willReturn($now);
        $user->method('getUpdatedAt')->willReturn($now);

        $person->method('getName')->willReturn('Test User');
        $person->method('getEmail')->willReturn('test@example.com');
        $person->method('getPhone')->willReturn('123456789');
        $person->method('getAvatarUrl')->willReturn('http://example.com/avatar.jpg');

        $person->method('getAvatarUrl')->willReturn('http://example.com/avatar.jpg');

        $cpfCnpj = CpfCnpj::fromString('111.444.777-35');

        $person->method('getCpfCnpj')->willReturn($cpfCnpj);

        $role->method('getId')->willReturn(2);
        $role->method('getName')->willReturn('user');

        $dto = UserProfileResponseDTO::fromEntity($user);

        $this->assertSame(1, $dto->id);
        $this->assertSame('Test User', $dto->name);
        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('123456789', $dto->phone);
        $this->assertSame($cpfCnpj->value(), $dto->cpfcnpj);
        $this->assertSame('http://example.com/avatar.jpg', $dto->avatarUrl);
        $this->assertTrue($dto->isActive);
        $this->assertTrue($dto->isVerified);
        $this->assertSame(2, $dto->roleId);
        $this->assertSame('user', $dto->roleName);
        $this->assertSame($now->format('Y-m-d H:i:s'), $dto->createdAt);
        $this->assertSame($now->format('Y-m-d H:i:s'), $dto->updatedAt);
    }

    public function testJsonSerialize(): void
    {
        $now = new DateTimeImmutable();
        $dto = new UserProfileResponseDTO(
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            phone: '123456789',
            cpfcnpj: '11144477735',
            avatarUrl: 'http://example.com/avatar.jpg',
            isActive: true,
            isVerified: true,
            roleId: 2,
            roleName: 'user',
            createdAt: $now->format('Y-m-d H:i:s'),
            updatedAt: $now->format('Y-m-d H:i:s'),
        );

        $json = $dto->jsonSerialize();

        $this->assertSame(1, $json['id']);
        $this->assertSame('Test User', $json['name']);
        $this->assertSame('test@example.com', $json['email']);
        $this->assertSame('123456789', $json['phone']);
        $this->assertSame('11144477735', $json['cpfcnpj']);
        $this->assertSame('http://example.com/avatar.jpg', $json['avatar_url']);
        $this->assertTrue($json['is_active']);
        $this->assertTrue($json['is_verified']);
        $this->assertSame(2, $json['role_id']);
        $this->assertSame('user', $json['role_name']);
        $this->assertSame($now->format('Y-m-d H:i:s'), $json['created_at']);
        $this->assertSame($now->format('Y-m-d H:i:s'), $json['updated_at']);
    }
}
