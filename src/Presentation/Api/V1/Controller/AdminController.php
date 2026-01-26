<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Application\DTO\CreateUserAdminRequestDTO;
use App\Application\DTO\UpdateUserAdminRequestDTO;
use App\Application\UseCase\CreateUserAdminUseCase;
use App\Application\UseCase\DeleteUserUseCase;
use App\Application\UseCase\GetUserUseCase;
use App\Application\UseCase\ListUsersUseCase;
use App\Application\UseCase\UpdateUserAdminUseCase;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Throwable;

class AdminController
{
    public function __construct(
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly LoggerInterface $logger,
        private readonly CreateUserAdminUseCase $createUserAdminUseCase,
        private readonly ListUsersUseCase $listUsersUseCase,
        private readonly GetUserUseCase $getUserUseCase,
        private readonly UpdateUserAdminUseCase $updateUserAdminUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
    ) {
    }

    public function createUser(Request $request): Response
    {
        $data = $request->getParsedBody();
        $dto = CreateUserAdminRequestDTO::fromArray($data);

        try {
            $user = $this->createUserAdminUseCase->execute($dto);

            return $this->jsonResponseFactory->success($user->toArray(), 'User created successfully.', 201);
        } catch (ConflictException | NotFoundException | ValidationException $e) {
            $this->logger->warning('Admin user creation failed: ' . $e->getMessage());

            $errors = $e instanceof ValidationException ? $e->getErrors() : null;
            return $this->jsonResponseFactory->fail($errors, $e->getMessage(), $e->getStatusCode());
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during admin user creation', ['exception' => $e]);

            return $this->jsonResponseFactory->error('An unexpected error occurred.');
        }
    }

    public function listUsers(Request $request): Response
    {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        $users = $this->listUsersUseCase->execute($limit, $offset);

        $usersArray = \array_map(fn ($user) => $user->toArray(), $users);

        return $this->jsonResponseFactory->success($usersArray);
    }

    public function getUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int)$args['id'];

        try {
            $user = $this->getUserUseCase->execute($userId);

            return $this->jsonResponseFactory->success($user->toArray());
        } catch (NotFoundException $notFoundException) {
            return $this->jsonResponseFactory->fail(null, $notFoundException->getMessage(), 404);
        }
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int)$args['id'];
        $data = $request->getParsedBody();
        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $data);

        try {
            $user = $this->updateUserAdminUseCase->execute($dto);

            return $this->jsonResponseFactory->success($user->toArray(), 'User updated successfully.');
        } catch (NotFoundException | ConflictException | ValidationException $e) {
            $this->logger->warning('Admin user update failed: ' . $e->getMessage());

            $errors = $e instanceof ValidationException ? $e->getErrors() : null;
            return $this->jsonResponseFactory->fail($errors, $e->getMessage(), $e->getStatusCode());
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during admin user update', ['exception' => $e]);

            return $this->jsonResponseFactory->error('An unexpected error occurred.');
        }
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int)$args['id'];
        $requestingUserId = $request->getAttribute('user_id');

        if ($userId === (int)$requestingUserId) {
            return $this->jsonResponseFactory->fail(null, 'Admins cannot delete their own account.', 403);
        }

        try {
            $this->deleteUserUseCase->execute($userId);

            return $this->jsonResponseFactory->success(null, 'User deleted successfully.');
        } catch (NotFoundException $e) {
            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during user deletion', ['exception' => $e]);

            return $this->jsonResponseFactory->error('An unexpected error occurred.');
        }
    }
}
