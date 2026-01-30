<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Api\V1\Controller;

use App\Application\DTO\CreateUserAdminRequestDTO;
use App\Application\DTO\UpdateUserAdminRequestDTO;
use App\Application\DTO\UserListResponseDTO;
use App\Application\DTO\UserResponseDTO;
use App\Application\UseCase\CreateUserAdminUseCase;
use App\Application\UseCase\DeleteUserUseCase;
use App\Application\UseCase\GetUserUseCase;
use App\Application\UseCase\ListUsersUseCase;
use App\Application\UseCase\UpdateUserAdminUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Presentation\Api\V1\Controller\AdminController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response as SlimResponse;

class AdminControllerTest extends TestCase
{
    private JsonResponseFactory&MockObject $jsonResponseFactory;
    private LoggerInterface&MockObject $logger;
    private CreateUserAdminUseCase&MockObject $createUserAdminUseCase;
    private ListUsersUseCase&MockObject $listUsersUseCase;
    private GetUserUseCase&MockObject $getUserUseCase;
    private UpdateUserAdminUseCase&MockObject $updateUserAdminUseCase;
    private DeleteUserUseCase&MockObject $deleteUserUseCase;
    private AdminController $adminController;
    private Response $response;

    protected function setUp(): void
    {
        $this->jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->createUserAdminUseCase = $this->createMock(CreateUserAdminUseCase::class);
        $this->listUsersUseCase = $this->createMock(ListUsersUseCase::class);
        $this->getUserUseCase = $this->createMock(GetUserUseCase::class);
        $this->updateUserAdminUseCase = $this->createMock(UpdateUserAdminUseCase::class);
        $this->deleteUserUseCase = $this->createMock(DeleteUserUseCase::class);

        $this->adminController = new AdminController(
            $this->jsonResponseFactory,
            $this->logger,
            $this->createUserAdminUseCase,
            $this->listUsersUseCase,
            $this->getUserUseCase,
            $this->updateUserAdminUseCase,
            $this->deleteUserUseCase
        );

        $this->response = (new ResponseFactory())->createResponse();
    }

    public function testCreateUserSuccess(): void
    {
        $requestBody = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role_id' => 2, // Example role_id
            'is_active' => true,
            'is_verified' => false,
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = CreateUserAdminRequestDTO::fromArray($requestBody);

        $userResponseDto = new UserResponseDTO(
            id: 1,
            name: 'New User',
            email: 'newuser@example.com',
            phone: null,
            cpfcnpj: null,
            roleName: 'member',
            isActive: true,
            isVerified: false,
        );

        $this->createUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($arg) use ($dto) {
                return $arg instanceof CreateUserAdminRequestDTO
                    && $arg->name === $dto->name
                    && $arg->email === $dto->email;
            }))
            ->willReturn($userResponseDto);

        $responseData = [
            'id' => $userResponseDto->id,
            'name' => $userResponseDto->name,
            'email' => $userResponseDto->email,
            'phone' => $userResponseDto->phone,
            'cpfcnpj' => $userResponseDto->cpfcnpj,
            'role_name' => $userResponseDto->roleName,
            'is_active' => $userResponseDto->isActive,
            'is_verified' => $userResponseDto->isVerified,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(201);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => 'Usuário criado com sucesso.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($responseData, 'Usuário criado com sucesso.', 201)
            ->willReturn($mockedResponse);

        $response = $this->adminController->createUser($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => 'Usuário criado com sucesso.'
        ]), (string)$response->getBody());
    }

    public function testCreateUserValidationException(): void
    {
        $requestBody = [
            'name' => '', // Invalid name
            'email' => 'invalid-email',
            'password' => 'short',
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = CreateUserAdminRequestDTO::fromArray($requestBody);
        $validationErrors = ['Name is required.', 'Email is invalid.'];

        $this->createUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($arg) use ($dto) {
                return $arg instanceof CreateUserAdminRequestDTO
                    && $arg->name === $dto->name
                    && $arg->email === $dto->email;
            }))
            ->willThrowException(new ValidationException('Validation failed', $validationErrors));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(400);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with($validationErrors, 'Validation failed', 422)
            ->willReturn($mockedResponse);

        $response = $this->adminController->createUser($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testCreateUserConflictException(): void
    {
        $requestBody = [
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role_id' => 2,
            'is_active' => true,
            'is_verified' => false,
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = CreateUserAdminRequestDTO::fromArray($requestBody);

        $this->createUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new ConflictException('Email already in use.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(409);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Email already in use.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Email already in use.', 409)
            ->willReturn($mockedResponse);

        $response = $this->adminController->createUser($request);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Email already in use.'
        ]), (string)$response->getBody());
    }

    public function testCreateUserGenericError(): void
    {
        $requestBody = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role_id' => 2,
            'is_active' => true,
            'is_verified' => false,
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = CreateUserAdminRequestDTO::fromArray($requestBody);

        $this->createUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]));
        // ✅ CORRIGIDO: Agora passa os 3 parâmetros esperados
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Ocorreu um erro inesperado.',  // message
                null,                            // data
                500                              // statusCode
            )
            ->willReturn($mockedResponse);

        $response = $this->adminController->createUser($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]), (string)$response->getBody());
    }

    public function testListUsersSuccess(): void
    {
        $limit = 10;
        $offset = 0;
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['limit' => $limit, 'offset' => $offset]);

        $userResponseDto1 = new UserResponseDTO(
            id: 1,
            name: 'User One',
            email: 'one@example.com',
            phone: null,
            cpfcnpj: null,
            roleName: 'user',
            isActive: true,
            isVerified: true,
        );
        $userResponseDto2 = new UserResponseDTO(
            id: 2,
            name: 'User Two',
            email: 'two@example.com',
            phone: null,
            cpfcnpj: null,
            roleName: 'admin',
            isActive: true,
            isVerified: true,
        );
        $userList = [$userResponseDto1, $userResponseDto2];
        $total = count($userList);

        $userListResponseDTO = new UserListResponseDTO($userList, $total, $limit, $offset);

        $this->listUsersUseCase->expects($this->once())
            ->method('execute')
            ->with($limit, $offset)
            ->willReturn($userListResponseDTO);

        $expectedUserData = [];
        foreach ($userList as $user) {
            $expectedUserData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roleName' => $user->roleName,
                'isActive' => $user->isActive,
                'isVerified' => $user->isVerified,
                'phone' => $user->phone,
                'cpfcnpj' => $user->cpfcnpj,
            ];
        }

        $expectedResponseData = [
            'users' => $expectedUserData,
            'total' => $userListResponseDTO->total,
            'limit' => $userListResponseDTO->limit,
            'offset' => $userListResponseDTO->offset,
        ];
        
        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => null
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($userListResponseDTO)
            ->willReturn($mockedResponse);

        $response = $this->adminController->listUsers($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => null
        ]), (string)$response->getBody());
    }

    public function testListUsersWithDefaultPagination(): void
    {
        $limit = 20;
        $offset = 0;
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([]); // No query params, use defaults

        $userListResponseDTO = new UserListResponseDTO([], 0, $limit, $offset);

        $this->listUsersUseCase->expects($this->once())
            ->method('execute')
            ->with($limit, $offset)
            ->willReturn($userListResponseDTO);
        
        $expectedResponseData = [
            'users' => [],
            'total' => $userListResponseDTO->total,
            'limit' => $userListResponseDTO->limit,
            'offset' => $userListResponseDTO->offset,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => null
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($userListResponseDTO)
            ->willReturn($mockedResponse);

        $response = $this->adminController->listUsers($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => null
        ]), (string)$response->getBody());
    }

    public function testGetUserSuccess(): void
    {
        $userId = 1;
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];

        $userResponseDto = new UserResponseDTO(
            id: $userId,
            name: 'Test User',
            email: 'test@example.com',
            phone: null,
            cpfcnpj: null,
            roleName: 'admin',
            isActive: true,
            isVerified: true,
        );

        $this->getUserUseCase->expects($this->once())
            ->method('execute')
            ->with($userId)
            ->willReturn($userResponseDto);

        $responseData = [
            'id' => $userResponseDto->id,
            'name' => $userResponseDto->name,
            'email' => $userResponseDto->email,
            'phone' => $userResponseDto->phone,
            'cpfcnpj' => $userResponseDto->cpfcnpj,
            'role_name' => $userResponseDto->roleName,
            'is_active' => $userResponseDto->isActive,
            'is_verified' => $userResponseDto->isVerified,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => null
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($responseData)
            ->willReturn($mockedResponse);

        $response = $this->adminController->getUser($request, $response, $args);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => null
        ]), (string)$response->getBody());
    }

    public function testGetUserNotFound(): void
    {
        $userId = 99;
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];

        $this->getUserUseCase->expects($this->once())
            ->method('execute')
            ->with($userId)
            ->willThrowException(new NotFoundException('User not found.'));

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User not found.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'User not found.', 404)
            ->willReturn($mockedResponse);

        $response = $this->adminController->getUser($request, $response, $args);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User not found.'
        ]), (string)$response->getBody());
    }

    public function testUpdateUserSuccess(): void
    {
        $userId = 1;
        $requestBody = [
            'name' => 'Updated Admin',
            'role_id' => 1, // admin role
            'is_active' => true,
        ];
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $requestBody);

        $userResponseDto = new UserResponseDTO(
            id: $userId,
            name: 'Updated Admin',
            email: 'admin@example.com',
            phone: null,
            cpfcnpj: null,
            roleName: 'admin',
            isActive: true,
            isVerified: true,
        );

        $this->updateUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($arg) use ($dto) {
                return $arg instanceof UpdateUserAdminRequestDTO
                    && $arg->userId === $dto->userId
                    && $arg->name === $dto->name;
            }))
            ->willReturn($userResponseDto);

        $responseData = [
            'id' => $userResponseDto->id,
            'name' => $userResponseDto->name,
            'email' => $userResponseDto->email,
            'phone' => $userResponseDto->phone,
            'cpfcnpj' => $userResponseDto->cpfcnpj,
            'role_name' => $userResponseDto->roleName,
            'is_active' => $userResponseDto->isActive,
            'is_verified' => $userResponseDto->isVerified,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => 'Usuário atualizado com sucesso.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($responseData, 'Usuário atualizado com sucesso.')
            ->willReturn($mockedResponse);

        $response = $this->adminController->updateUser($request, $response, $args);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $responseData,
            'message' => 'Usuário atualizado com sucesso.'
        ]), (string)$response->getBody());
    }

    public function testUpdateUserValidationException(): void
    {
        $userId = 1;
        $requestBody = [
            'name' => '', // Invalid name
            'email' => 'invalid-email',
        ];
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $requestBody);
        $validationErrors = ['Name is required.', 'Email is invalid.'];

        $this->updateUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new ValidationException('Validation failed', $validationErrors));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(400);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with($validationErrors, 'Validation failed', 422)
            ->willReturn($mockedResponse);

        $response = $this->adminController->updateUser($request, $response, $args);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testUpdateUserConflictException(): void
    {
        $userId = 1;
        $requestBody = [
            'email' => 'existing@example.com', // Conflicting email
        ];
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $requestBody);

        $this->updateUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new ConflictException('Email already in use.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(409);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Email already in use.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Email already in use.', 409)
            ->willReturn($mockedResponse);

        $response = $this->adminController->updateUser($request, $response, $args);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Email already in use.'
        ]), (string)$response->getBody());
    }

    public function testUpdateUserNotFoundException(): void
    {
        $userId = 99;
        $requestBody = ['name' => 'Non Existent'];
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $requestBody);

        $this->updateUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new NotFoundException('User not found.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User not found.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'User not found.', 404)
            ->willReturn($mockedResponse);

        $response = $this->adminController->updateUser($request, $response, $args);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User not found.'
        ]), (string)$response->getBody());
    }

    public function testUpdateUserGenericError(): void
    {
        $userId = 1;
        $requestBody = ['name' => 'Test'];
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userId];
        $request->method('getParsedBody')->willReturn($requestBody);

        $dto = UpdateUserAdminRequestDTO::fromArray($userId, $requestBody);

        $this->updateUserAdminUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]));
        // ✅ CORRIGIDO: Agora passa os 3 parâmetros esperados
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Ocorreu um erro inesperado.',  // message
                null,                            // data
                500                              // statusCode
            )
            ->willReturn($mockedResponse);

        $response = $this->adminController->updateUser($request, $response, $args);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]), (string)$response->getBody());
    }

    public function testDeleteUserSuccess(): void
    {
        $userIdToDelete = 2;
        $requestingUserId = 1; // Admin user
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userIdToDelete];
        $request->method('getAttribute')->with('user_id')->willReturn($requestingUserId);

        $this->deleteUserUseCase->expects($this->once())
            ->method('execute')
            ->with($userIdToDelete);

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Usuário excluído com sucesso.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(null, 'Usuário excluído com sucesso.')
            ->willReturn($mockedResponse);

        $response = $this->adminController->deleteUser($request, $response, $args);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Usuário excluído com sucesso.'
        ]), (string)$response->getBody());
    }

    public function testDeleteUserAdminCannotDeleteOwnAccount(): void
    {
        $userIdToDelete = 1;
        $requestingUserId = 1; // Admin tries to delete own account
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userIdToDelete];
        $request->method('getAttribute')->with('user_id')->willReturn($requestingUserId);

        $this->deleteUserUseCase->expects($this->never())->method('execute'); // Should not be called

        $mockedResponse = (new ResponseFactory())->createResponse(403);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Administradores não podem excluir a própria conta.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Administradores não podem excluir a própria conta.', 403)
            ->willReturn($mockedResponse);

        $response = $this->adminController->deleteUser($request, $response, $args);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Administradores não podem excluir a própria conta.'
        ]), (string)$response->getBody());
    }

    public function testDeleteUserNotFound(): void
    {
        $userIdToDelete = 99;
        $requestingUserId = 1;
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userIdToDelete];
        $request->method('getAttribute')->with('user_id')->willReturn($requestingUserId);

        $this->deleteUserUseCase->expects($this->once())
            ->method('execute')
            ->with($userIdToDelete)
            ->willThrowException(new NotFoundException('User to delete not found.'));

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User to delete not found.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'User to delete not found.', 404)
            ->willReturn($mockedResponse);

        $response = $this->adminController->deleteUser($request, $response, $args);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'User to delete not found.'
        ]), (string)$response->getBody());
    }

    public function testDeleteUserGenericError(): void
    {
        $userIdToDelete = 2;
        $requestingUserId = 1;
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$userIdToDelete];
        $request->method('getAttribute')->with('user_id')->willReturn($requestingUserId);

        $this->deleteUserUseCase->expects($this->once())
            ->method('execute')
            ->with($userIdToDelete)
            ->willThrowException(new \Exception('Database error.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]));
        // ✅ CORRIGIDO: Agora passa os 3 parâmetros esperados
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'Ocorreu um erro inesperado.',  // message
                null,                            // data
                500                              // statusCode
            )
            ->willReturn($mockedResponse);

        $response = $this->adminController->deleteUser($request, $response, $args);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Ocorreu um erro inesperado.'
        ]), (string)$response->getBody());
    }
}