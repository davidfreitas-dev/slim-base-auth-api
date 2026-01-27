<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\PersonResponseDTO;
use App\Application\DTO\UpdateUserProfileRequestDTO;
use App\Domain\Entity\Person;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use Exception;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UpdateUserProfileUseCase
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly string $uploadPath,
    ) {
    }

    public function execute(UpdateUserProfileRequestDTO $dto): PersonResponseDTO
    {
        $user = $this->userRepository->findById($dto->userId);
        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('User not found');
        }

        $person = $user->getPerson();
        $oldAvatarUrl = $person->getAvatarUrl(); // Store old avatar URL for potential deletion

        $this->pdo->beginTransaction();

        try {
            // Update personal information
            if ($dto->name !== null) {
                $person->setName($dto->name);
            }

            if ($dto->email !== null) {
                // Check if email already exists for another person
                $existingPerson = $this->personRepository->findByEmail($dto->email);
                if ($existingPerson && $existingPerson->getId() !== $person->getId()) {
                    throw new ValidationException('Email already registered by another user.');
                }

                $person->setEmail($dto->email);
            }

            if ($dto->phone !== null) {
                $sanitizedPhone = \preg_replace('/[^0-9]/', '', $dto->phone);
                $person->setPhone($sanitizedPhone);
            }

            if ($dto->cpfcnpj !== null) {
                $sanitizedCpfCnpj = CpfCnpj::fromString($dto->cpfcnpj);
                // Check if CPF/CNPJ already exists for another person
                $existingPerson = $this->personRepository->findByCpfCnpj($sanitizedCpfCnpj);
                if ($existingPerson && $existingPerson->getId() !== $person->getId()) {
                    throw new ValidationException('CPF/CNPJ already registered by another user.');
                }

                $person->setCpfCnpj($sanitizedCpfCnpj);
            }

            // Handle profile image upload
            if ($dto->profileImage instanceof \Psr\Http\Message\UploadedFileInterface && $dto->profileImage->getError() === UPLOAD_ERR_OK) {
                $this->handleProfileImageUpload($person, $dto->profileImage);
            }

            $user->touch(); // Update the 'updatedAt' timestamp on the User entity

            $updatedUser = $this->userRepository->update($user);

            $this->pdo->commit();

            $updatedPerson = $updatedUser->getPerson();

            // Delete the old file only after successful commit
            if ($oldAvatarUrl && \file_exists($oldAvatarUrl) && $oldAvatarUrl !== $updatedPerson->getAvatarUrl()) {
                \unlink($oldAvatarUrl);
            }

            return new PersonResponseDTO(
                id: $updatedPerson->getId(),
                name: $updatedPerson->getName(),
                email: $updatedPerson->getEmail(),
                phone: $updatedPerson->getPhone(),
                cpfcnpj: $updatedPerson->getCpfCnpj()?->value(),
                avatarUrl: $updatedPerson->getAvatarUrl(),
            );
        } catch (Exception $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    private function handleProfileImageUpload(Person $person, UploadedFileInterface $uploadedFile): void
    {
        // Generate a new unique filename
        $extension = \pathinfo((string) $uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = \sprintf('%d-%d.%s', $person->getId(), \time(), $extension);
        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . $filename;

        // Ensure the upload directory exists
        if (!\is_dir($this->uploadPath)) {
            \mkdir($this->uploadPath, 0o775, true);
        }

        // Move the file
        try {
            $uploadedFile->moveTo($destinationPath);
        } catch (Exception $exception) {
            throw new RuntimeException('Failed to move uploaded file.', 0, $exception);
        }

        // Update the person's profile image path
        $person->setAvatarUrl($destinationPath);
    }
}
