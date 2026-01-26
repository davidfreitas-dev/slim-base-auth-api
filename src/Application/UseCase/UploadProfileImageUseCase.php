<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Exception;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadProfileImageUseCase
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly string $uploadPath,
    ) {
    }

    public function execute(int $userId, UploadedFileInterface $uploadedFile): string
    {
        $user = $this->userRepository->findById($userId);
        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('User not found');
        }

        $this->validateUploadedFile($uploadedFile);

        $person = $user->getPerson();
        $oldImagePath = $person->getAvatarUrl();

        // Generate a new unique filename
        $extension = \pathinfo((string) $uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = \sprintf('%d-%d.%s', $user->getId(), \time(), $extension);
        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . $filename;

        // Move the file
        try {
            $uploadedFile->moveTo($destinationPath);
        } catch (Exception) {
            // Log the exception if needed
            throw new RuntimeException('Failed to move uploaded file.');
        }

        // Delete the old file if it exists
        if ($oldImagePath && \file_exists($oldImagePath)) {
            \unlink($oldImagePath);
        }

        // Update the person's profile image path
        $person->setAvatarUrl($destinationPath);
        $this->personRepository->update($person);

        return $destinationPath;
    }

    private function validateUploadedFile(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Failed to upload file. Error code: ' . $file->getError());
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new ValidationException('File size exceeds the maximum limit of 2MB.');
        }

        if (!\in_array($file->getClientMediaType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
        }
    }
}
