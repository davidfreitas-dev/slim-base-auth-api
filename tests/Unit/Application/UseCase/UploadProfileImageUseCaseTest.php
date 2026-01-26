<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\UseCase\UploadProfileImageUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Tests\TestCase;

class UploadProfileImageUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $personRepository;

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

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getSize')->willReturn(1024); // 1KB
        $uploadedFile->method('getClientMediaType')->willReturn('image/png');
        $uploadedFile->method('getClientFilename')->willReturn('avatar.png');
        $uploadedFile->expects($this->once())->method('moveTo');

        $person = $this->createMock(Person::class);
        $person->expects($this->once())->method('setAvatarUrl');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getPerson')->willReturn($person);
        $this->userRepository->method('findById')->with($userId)->willReturn($user);

        $this->personRepository->expects($this->once())->method('update')->with($person);

        $resultPath = $this->uploadProfileImageUseCase->execute($userId, $uploadedFile);

        $this->assertStringContainsString($this->uploadPath, $resultPath);
    }

    public function testShouldThrowNotFoundExceptionForUnknownUser(): void
    {
        $this->expectException(NotFoundException::class);
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $this->userRepository->method('findById')->with(999)->willReturn(null);
        $this->uploadProfileImageUseCase->execute(999, $uploadedFile);
    }

    public function testShouldThrowValidationExceptionForFileUploadError(): void
    {
        $this->expectException(ValidationException::class);
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

        $this->uploadProfileImageUseCase->execute(1, $uploadedFile);
    }

    public function testShouldThrowValidationExceptionForFileSizeTooLarge(): void
    {
        $this->expectException(ValidationException::class);
        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFile->method('getSize')->willReturn(5 * 1024 * 1024); // 5MB

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

        $this->uploadProfileImageUseCase->execute(1, $uploadedFile);
    }
}
