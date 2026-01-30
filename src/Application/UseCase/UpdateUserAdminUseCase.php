<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UpdateUserAdminRequestDTO;
use App\Application\DTO\UserResponseDTO;
use App\Domain\Entity\Role;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use Exception;
use PDO;

class UpdateUserAdminUseCase
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {
    }

    public function execute(UpdateUserAdminRequestDTO $dto): UserResponseDTO
    {
        $this->pdo->beginTransaction();

        try {
            $user = $this->userRepository->findById($dto->userId);
            if (!$user instanceof \App\Domain\Entity\User) {
                throw new NotFoundException('Usuário não encontrado.');
            }

            // Check for email conflicts
            $existingPersonWithEmail = $this->personRepository->findByEmail($dto->email);
            if ($existingPersonWithEmail && $existingPersonWithEmail->getId() !== $user->getPerson()->getId()) {
                throw new ConflictException('O e-mail já está em uso por outro usuário.');
            }

            // Check for CPF/CNPJ conflicts
            if ($dto->cpfcnpj) {
                $existingPersonWithCpfCnpj = $this->personRepository->findByCpfCnpj($dto->cpfcnpj);
                if ($existingPersonWithCpfCnpj && $existingPersonWithCpfCnpj->getId() !== $user->getPerson()->getId()) {
                    throw new ConflictException('O CPF/CNPJ já está em uso por outro usuário.');
                }
            }

            // Find and set the new role
            $role = $this->roleRepository->findByName($dto->roleName);
            if (!$role instanceof Role) {
                throw new NotFoundException(sprintf("O perfil '%s' não foi encontrado.", $dto->roleName));
            }

            $user->setRole($role);

            // Update person details
            $person = $user->getPerson();
            $person->setName($dto->name);
            $person->setEmail($dto->email);
            $person->setPhone($dto->phone);
            $person->setCpfCnpj($dto->cpfcnpj !== null ? CpfCnpj::fromString($dto->cpfcnpj) : null);
            $this->personRepository->update($person);

            // Update user status (active/verified)
            if ($dto->isActive !== null) {
                $dto->isActive ? $user->activate() : $user->deactivate();
            }

            if ($dto->isVerified !== null) {
                $dto->isVerified ? $user->markAsVerified() : $user->markAsUnverified();
            }

            // Persist user changes
            $updatedUser = $this->userRepository->update($user);

            $this->pdo->commit();

            return new UserResponseDTO(
                id: $updatedUser->getId(),
                name: $updatedUser->getPerson()->getName(),
                email: $updatedUser->getPerson()->getEmail(),
                roleName: $updatedUser->getRole()->getName(),
                isActive: $updatedUser->isActive(),
                isVerified: $updatedUser->isVerified(),
                phone: $updatedUser->getPerson()->getPhone(),
                cpfcnpj: $updatedUser->getPerson()->getCpfCnpj() ? $updatedUser->getPerson()->getCpfCnpj()->value() : null,
            );
        } catch (Exception $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
