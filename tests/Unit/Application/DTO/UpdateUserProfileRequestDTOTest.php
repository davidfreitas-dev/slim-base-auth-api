<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\UpdateUserProfileRequestDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\UploadedFileInterface;
use Tests\TestCase;

#[CoversClass(UpdateUserProfileRequestDTO::class)]
class UpdateUserProfileRequestDTOTest extends TestCase
{
    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '(11) 98888-8888',
            'cpfcnpj' => '123.456.789-01',
        ];
        $userId = 1;

        $dto = UpdateUserProfileRequestDTO::fromArray($data, $userId);

        $this->assertSame($userId, $dto->userId);
        $this->assertSame($data['name'], $dto->name);
        $this->assertSame($data['email'], $dto->email);
        $this->assertSame($data['phone'], $dto->phone);
        $this->assertSame($data['cpfcnpj'], $dto->cpfcnpj);
        $this->assertNull($dto->profileImage);
    }

    public function testFromArrayWithSomeFields(): void
    {
        $data = [
            'name' => 'Test User',
        ];
        $userId = 1;

        $dto = UpdateUserProfileRequestDTO::fromArray($data, $userId);

        $this->assertSame($userId, $dto->userId);
        $this->assertSame($data['name'], $dto->name);
        $this->assertNull($dto->email);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->cpfcnpj);
        $this->assertNull($dto->profileImage);
    }

    public function testFromArrayWithProfileImage(): void
    {
        $data = [
            'name' => 'Test User',
        ];
        $userId = 1;
        $profileImage = $this->createMock(UploadedFileInterface::class);

        $dto = UpdateUserProfileRequestDTO::fromArray($data, $userId, $profileImage);

        $this->assertSame($profileImage, $dto->profileImage);
    }

    public function testToArray(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '11988888888',
            'cpfcnpj' => '12345678901',
        ];
        $userId = 1;
        $profileImage = $this->createMock(UploadedFileInterface::class);

        $dto = UpdateUserProfileRequestDTO::fromArray($data, $userId, $profileImage);
        $dtoArray = $dto->toArray();

        $this->assertSame($userId, $dtoArray['user_id']);
        $this->assertSame($data['name'], $dtoArray['name']);
        $this->assertSame($data['email'], $dtoArray['email']);
        $this->assertSame($data['phone'], $dtoArray['phone']);
        $this->assertSame($data['cpfcnpj'], $dtoArray['cpfcnpj']);
        $this->assertSame($profileImage, $dtoArray['profile_image']);
    }

    public function testValidateProfileImageSuccess(): void
    {
        $profileImage = $this->createMock(UploadedFileInterface::class);
        $profileImage->method('getError')->willReturn(UPLOAD_ERR_OK);
        $profileImage->method('getSize')->willReturn(1024 * 1024); // 1MB
        $profileImage->method('getClientMediaType')->willReturn('image/jpeg');

        $dto = new UpdateUserProfileRequestDTO(1, null, null, null, null, $profileImage);
        
        $this->assertNull($dto->validateProfileImage());
    }

    public function testValidateProfileImageTooLarge(): void
    {
        $profileImage = $this->createMock(UploadedFileInterface::class);
        $profileImage->method('getError')->willReturn(UPLOAD_ERR_OK);
        $profileImage->method('getSize')->willReturn(3 * 1024 * 1024); // 3MB
        $profileImage->method('getClientMediaType')->willReturn('image/jpeg');

        $dto = new UpdateUserProfileRequestDTO(1, null, null, null, null, $profileImage);
        
        $this->assertStringContainsString('muito grande', $dto->validateProfileImage());
    }

    public function testValidateProfileImageInvalidType(): void
    {
        $profileImage = $this->createMock(UploadedFileInterface::class);
        $profileImage->method('getError')->willReturn(UPLOAD_ERR_OK);
        $profileImage->method('getSize')->willReturn(1024 * 1024);
        $profileImage->method('getClientMediaType')->willReturn('application/pdf');

        $dto = new UpdateUserProfileRequestDTO(1, null, null, null, null, $profileImage);
        
        $this->assertStringContainsString('inválido', $dto->validateProfileImage());
    }
}