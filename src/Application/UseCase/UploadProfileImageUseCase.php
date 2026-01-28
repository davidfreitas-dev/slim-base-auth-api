<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UserProfileResponseDTO;
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

    public function execute(int $userId, UploadedFileInterface $uploadedFile): UserProfileResponseDTO
    {
        $user = $this->userRepository->findById($userId);
        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('Usuário não encontrado.');
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
            throw new RuntimeException('Falha ao mover o arquivo enviado.');
        }

        // Delete the old file if it exists
        if ($oldImagePath && \file_exists($oldImagePath)) {
            \unlink($oldImagePath);
        }

        // Update the person's profile image path
        $person->setAvatarUrl($destinationPath);
        $this->personRepository->update($person);

        // Fetch the updated user to get the latest person data for the DTO
        $updatedUser = $this->userRepository->findById($userId);
        if (!$updatedUser instanceof \App\Domain\Entity\User) {
            // This case should ideally not happen if update($person) was successful
            throw new RuntimeException('Falha ao recuperar os dados atualizados do usuário.');
        }

        return new UserProfileResponseDTO(
            id: $updatedUser->getId(), // Use $updatedUser->getId() for consistency and non-nullable type
            name: $updatedUser->getPerson()->getName(),
            email: $updatedUser->getPerson()->getEmail(),
            phone: $updatedUser->getPerson()->getPhone(),
            cpfcnpj: $updatedUser->getPerson()->getCpfCnpj()?->value(),
            avatarUrl: $updatedUser->getPerson()->getAvatarUrl(),
            isActive: $updatedUser->isActive(),
            isVerified: $updatedUser->isVerified(),
            roleId: $updatedUser->getRole()->getId(),
            roleName: $updatedUser->getRole()->getName(),
            createdAt: $updatedUser->getCreatedAt()->format('Y-m-d H:i:s'),
            updatedAt: $updatedUser->getUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    private function validateUploadedFile(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Falha ao enviar o arquivo. Código do erro: ' . $file->getError());
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new ValidationException('O tamanho do arquivo excede o limite máximo de 2MB.');
        }

        if (!\in_array($file->getClientMediaType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException('Tipo de arquivo inválido. Apenas JPEG, PNG e GIF são permitidos.');
        }
    }
}
