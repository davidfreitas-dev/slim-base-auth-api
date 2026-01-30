<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use Exception;
use PDO;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly JwtService $jwtService,
        private readonly PDO $pdo,
    ) {
    }

    public function execute(int $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if (!$user instanceof \App\Domain\Entity\User) {
            throw new NotFoundException('Usuário não encontrado.');
        }

        $this->pdo->beginTransaction();

        try {
            $this->userRepository->delete($userId);
            $this->personRepository->delete($userId);
            $this->jwtService->invalidateAllUserRefreshTokens($userId);

            $this->pdo->commit();
        } catch (Exception $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
