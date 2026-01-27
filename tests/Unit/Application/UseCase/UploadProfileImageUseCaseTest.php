<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\UserProfileResponseDTO;
use App\Application\UseCase\UploadProfileImageUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Tests\TestCase;

class UploadProfileImageUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    
    private PersonRepositoryInterface&MockObject $personRepository;

    private UploadProfileImageUseCase $uploadProfileImageUseCase;

    private string $uploadPath = '/tmp/test_uploads';

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);

        // Ensure upload directory exists and is clean
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0777, true);
        }

        $this->uploadProfileImageUseCase = new UploadProfileImageUseCase(
            $this->userRepository,
            $this->personRepository,
            $this->uploadPath
        );
    }

    protected function tearDown(): void
    {
        // Clean up dummy files and directory
        $files = glob($this->uploadPath . '/*');
        foreach($files as $file){
            if(is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->uploadPath)) {
            rmdir($this->uploadPath);
        }

        parent::tearDown();
    }

    public function testShouldUploadImageSuccessfully(): void
    {
        $userId = 1;
        $mockPersonId = 1;
        $mockUserName = 'Test User';
        $mockUserEmail = 'test@example.com';
        $mockUserPhone = '1234567890';
        $mockUserCpfCnpj = '123.456.789-00';
        $mockUserRoleId = 1;
        $mockUserRoleName = 'user';
        $mockCreatedAt = new DateTimeImmutable('-1 day');
        $mockUpdatedAt = new DateTimeImmutable();

        /** @var UploadedFileInterface&MockObject $uploadedFile */
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getSize')->willReturn(1024); // 1KB
        $uploadedFile->method('getClientMediaType')->willReturn('image/png');
        $uploadedFile->method('getClientFilename')->willReturn('avatar.png');
        $uploadedFile->expects($this->once())->method('moveTo');

        /** @var Person&MockObject $person */
        $person = $this->createMock(Person::class);
        $person->method('getId')->willReturn($mockPersonId);
        $person->method('getName')->willReturn($mockUserName);
        $person->method('getEmail')->willReturn($mockUserEmail);
        $person->method('getPhone')->willReturn($mockUserPhone);
        $person->method('getCpfCnpj')->willReturn(null);

        // Store the avatarUrl internally within the mock to simulate state change
        $mockAvatarUrl = null;
        $person->method('getAvatarUrl')->willReturnCallback(function () use (&$mockAvatarUrl) {
            return $mockAvatarUrl;
        });
        $person->expects($this->once())
               ->method('setAvatarUrl')
               ->willReturnCallback(function (?string $avatarUrl) use (&$mockAvatarUrl) {
                   $mockAvatarUrl = $avatarUrl;
               });

        /** @var Role&MockObject $role */
        $role = $this->createMock(Role::class);
        $role->method('getId')->willReturn($mockUserRoleId);
        $role->method('getName')->willReturn($mockUserRoleName);

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getPerson')->willReturn($person);
        $user->method('getRole')->willReturn($role);
        $user->method('isActive')->willReturn(true);
        $user->method('isVerified')->willReturn(true);
        $user->method('getCreatedAt')->willReturn($mockCreatedAt);
        $user->method('getUpdatedAt')->willReturn($mockUpdatedAt);

        $this->userRepository->method('findById')->with($userId)->willReturnOnConsecutiveCalls($user, $user);

        $this->personRepository->expects($this->once())->method('update')->with($person);

        $result = $this->uploadProfileImageUseCase->execute($userId, $uploadedFile);

        $this->assertInstanceOf(UserProfileResponseDTO::class, $result);
        $this->assertStringContainsString($this->uploadPath, $result->avatarUrl);
        $this->assertEquals($userId, $result->id);
        $this->assertEquals($mockUserName, $result->name);
        $this->assertEquals($mockUserEmail, $result->email);
        $this->assertEquals($mockUserPhone, $result->phone);
        $this->assertEquals($mockUserRoleName, $result->roleName);
        $this->assertTrue($result->isActive);
        $this->assertTrue($result->isVerified);
    }

    public function testShouldThrowNotFoundExceptionForUnknownUser(): void
    {
        $this->expectException(NotFoundException::class);
        
        /** @var UploadedFileInterface&MockObject $uploadedFile */
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $this->userRepository->method('findById')->with(999)->willReturn(null);
        $this->uploadProfileImageUseCase->execute(999, $uploadedFile);
    }

    public function testShouldThrowValidationExceptionForFileUploadError(): void
    {
        $this->expectException(ValidationException::class);
        
        /** @var UploadedFileInterface&MockObject $uploadedFile */
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

        $this->uploadProfileImageUseCase->execute(1, $uploadedFile);
    }

    public function testShouldThrowValidationExceptionForFileSizeTooLarge(): void
    {
        $this->expectException(ValidationException::class);
        
        /** @var UploadedFileInterface&MockObject $uploadedFile */
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getSize')->willReturn(5 * 1024 * 1024); // 5MB

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

        $this->uploadProfileImageUseCase->execute(1, $uploadedFile);
    }
}