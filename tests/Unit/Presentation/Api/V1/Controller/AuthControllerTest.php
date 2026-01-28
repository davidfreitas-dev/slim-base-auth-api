<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Api\V1\Controller;

use App\Application\DTO\PasswordResetResponseDTO;
use App\Application\DTO\LoginResponseDTO;
use App\Application\DTO\RegisterUserRequestDTO;
use App\Application\DTO\UserResponseDTO;
use App\Application\Exception\EmailSendingFailedException;
use App\Application\Service\ValidationService;
use App\Application\UseCase\ForgotPasswordUseCase;
use App\Application\UseCase\LoginUseCase;
use App\Application\UseCase\RegisterUserUseCase;
use App\Application\UseCase\ResetPasswordUseCase;
use App\Application\UseCase\ValidateResetCodeUseCase;
use App\Application\UseCase\VerifyEmailUseCase;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Infrastructure\Security\JwtService;
use App\Presentation\Api\V1\Controller\AuthController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response as SlimResponse;


class AuthControllerTest extends TestCase
{
    private RegisterUserUseCase&MockObject $registerUseCase;
    private LoginUseCase&MockObject $loginUseCase;
    private ForgotPasswordUseCase&MockObject $forgotPasswordUseCase;
    private ResetPasswordUseCase&MockObject $resetPasswordUseCase;
    private ValidateResetCodeUseCase&MockObject $validateResetCodeUseCase;
    private VerifyEmailUseCase&MockObject $verifyEmailUseCase;
    private UserRepositoryInterface&MockObject $userRepository;
    private JwtService&MockObject $jwtService;
    private LoggerInterface&MockObject $logger;
    private JsonResponseFactory&MockObject $jsonResponseFactory;
    private ValidationService&MockObject $validationService;
    private AuthController $authController;
    private Response $response;

    protected function setUp(): void
    {
        $this->registerUseCase = $this->createMock(RegisterUserUseCase::class);
        $this->loginUseCase = $this->createMock(LoginUseCase::class);
        $this->forgotPasswordUseCase = $this->createMock(ForgotPasswordUseCase::class);
        $this->resetPasswordUseCase = $this->createMock(ResetPasswordUseCase::class);
        $this->validateResetCodeUseCase = $this->createMock(ValidateResetCodeUseCase::class);
        $this->verifyEmailUseCase = $this->createMock(VerifyEmailUseCase::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->jwtService = $this->createMock(JwtService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $this->validationService = $this->createMock(ValidationService::class);
        
        $this->authController = new AuthController(
            $this->registerUseCase,
            $this->loginUseCase,
            $this->forgotPasswordUseCase,
            $this->resetPasswordUseCase,
            $this->validateResetCodeUseCase,
            $this->verifyEmailUseCase,
            $this->userRepository,
            $this->jwtService,
            $this->logger,
            $this->jsonResponseFactory,
            $this->validationService
        );

        $this->response = (new ResponseFactory())->createResponse();
    }

    public function testRegisterSuccess(): void
    {
        $requestBody = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '11987654321',
            'cpfcnpj' => '12345678901'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $registerUserRequestDTO = RegisterUserRequestDTO::fromArray($requestBody);
        $this->validationService->expects($this->once())->method('validate')->with($this->callback(function($dto) use ($registerUserRequestDTO) {
            return $dto->name === $registerUserRequestDTO->name && $dto->email === $registerUserRequestDTO->email;
        }));

        $userResponseDto = new UserResponseDTO(
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            roleName: 'user',
            isActive: true,
            isVerified: false,
            phone: '11987654321',
            cpfcnpj: '12345678901'
        );

        $this->registerUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($dto) use ($registerUserRequestDTO) {
                return $dto->name === $registerUserRequestDTO->name && $dto->email === $registerUserRequestDTO->email;
            }))
            ->willReturn($userResponseDto);

        $this->jwtService->expects($this->once())->method('generateAccessToken')->with(1, 'test@example.com')->willReturn('mock_access_token');
        $this->jwtService->expects($this->once())->method('generateRefreshToken')->with(1)->willReturn('mock_refresh_token');
        $this->jwtService->expects($this->once())->method('getAccessTokenExpire')->willReturn(3600);

        $expectedResponseData = [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        // Mock the JsonResponseFactory's success method
        $mockedResponse = (new ResponseFactory())->createResponse(201);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'User registered and logged in successfully. Please check your email to verify your account.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(
                $expectedResponseData,
                'User registered and logged in successfully. Please check your email to verify your account.',
                201
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->register($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'User registered and logged in successfully. Please check your email to verify your account.'
        ]), (string)$response->getBody());
    }

    public function testRegisterConflictException(): void
    {
        $requestBody = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'phone' => '11987654321',
            'cpfcnpj' => '12345678901'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $registerUserRequestDTO = RegisterUserRequestDTO::fromArray($requestBody);
        $this->validationService->expects($this->once())->method('validate');

        $this->registerUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new ConflictException('O e-mail já está cadastrado.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(409);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'O e-mail já está cadastrado.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'O e-mail já está cadastrado.', 409)
            ->willReturn($mockedResponse);

        $response = $this->authController->register($request);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'O e-mail já está cadastrado.'
        ]), (string)$response->getBody());
    }

    public function testRegisterValidationException(): void
    {
        $requestBody = [
            'name' => '', // Invalid name to trigger validation exception
            'email' => 'invalid-email',
            'password' => 'short',
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $validationErrors = ['O nome é obrigatório.', 'O e-mail "{{ value }}" não é um e-mail válido.'];
        $this->validationService->expects($this->once())
            ->method('validate')
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
            ->with($validationErrors, 'Validation failed', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->register($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testRegisterEmailSendingFailedException(): void
    {
        $requestBody = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '11987654321',
            'cpfcnpj' => '12345678901'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $registerUserRequestDTO = RegisterUserRequestDTO::fromArray($requestBody);
        $this->validationService->expects($this->once())->method('validate');

        $userResponseDto = new UserResponseDTO(
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            roleName: 'user',
            isActive: true,
            isVerified: false,
            phone: '11987654321',
            cpfcnpj: '12345678901'
        );
        $this->registerUseCase->expects($this->once())->method('execute')->willReturn($userResponseDto);

        $this->jwtService->expects($this->once())->method('generateAccessToken')->willReturn('mock_access_token');
        $this->jwtService->expects($this->once())->method('generateRefreshToken')->willReturn('mock_refresh_token');
        $this->jwtService->expects($this->once())->method('getAccessTokenExpire')->willReturn(3600);

        // Simulate EmailSendingFailedException after successful registration and token generation
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->willThrowException(new EmailSendingFailedException('Failed to send welcome email.'));

        $this->logger->expects($this->once())->method('error'); // Should log the error

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'User registered, but failed to send welcome email.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'User registered, but failed to send welcome email.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->register($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'User registered, but failed to send welcome email.'
        ]), (string)$response->getBody());
    }

    public function testRegisterGenericError(): void
    {
        $requestBody = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '11987654321',
            'cpfcnpj' => '12345678901'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())->method('validate');

        $this->registerUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Something unexpected happened.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->register($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testLoginSuccess(): void
    {
        $requestBody = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())->method('validate');

        $loginResponseDto = new LoginResponseDTO(
            accessToken: 'mock_access_token',
            refreshToken: 'mock_refresh_token',
            tokenType: 'Bearer',
            expiresIn: 3600
        );

        $this->loginUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($dto) use ($requestBody) {
                return $dto->email === $requestBody['email'] && $dto->password === $requestBody['password'];
            }))
            ->willReturn($loginResponseDto);

        $expectedResponseData = [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Login successful'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($expectedResponseData, 'Login successful')
            ->willReturn($mockedResponse);

        $response = $this->authController->login($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Login successful'
        ]), (string)$response->getBody());
    }

    public function testLoginValidationException(): void
    {
        $requestBody = [
            'email' => 'invalid-email',
            'password' => ''
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $validationErrors = ['O e-mail "{{ value }}" não é um e-mail válido.', 'A senha é obrigatória.'];
        $this->validationService->expects($this->once())
            ->method('validate')
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
            ->with($validationErrors, 'Validation failed', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->login($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testLoginAuthenticationException(): void
    {
        $requestBody = [
            'email' => 'wrong@example.com',
            'password' => 'wrong_password'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())->method('validate');

        $this->loginUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \App\Domain\Exception\AuthenticationException('Credenciais inválidas.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(401);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Credenciais inválidas.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Credenciais inválidas.', 401)
            ->willReturn($mockedResponse);

        $response = $this->authController->login($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Credenciais inválidas.'
        ]), (string)$response->getBody());
    }

    public function testLoginGenericError(): void
    {
        $requestBody = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())->method('validate');

        $this->loginUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Something unexpected happened.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->login($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testRefreshSuccess(): void
    {
        $refreshToken = 'valid_refresh_token';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $decodedToken = (object)['type' => 'refresh', 'jti' => 'jwt_id_123', 'sub' => 1];
        $this->jwtService->expects($this->once())->method('validateToken')->with($refreshToken)->willReturn($decodedToken);
        $this->jwtService->expects($this->once())->method('isRefreshTokenValid')->with('jwt_id_123')->willReturn(true);

        $userMock = $this->createMock(\App\Domain\Entity\User::class);
        $personMock = $this->createMock(\App\Domain\Entity\Person::class);
        $personMock->method('getEmail')->willReturn('test@example.com');
        $userMock->method('getId')->willReturn(1);
        $userMock->method('getPerson')->willReturn($personMock);

        $this->userRepository->expects($this->once())->method('findById')->with(1)->willReturn($userMock);
        
        $this->jwtService->expects($this->once())->method('revokeRefreshToken')->with('jwt_id_123');
        $this->jwtService->expects($this->once())->method('generateAccessToken')->with(1, 'test@example.com')->willReturn('new_access_token');
        $this->jwtService->expects($this->once())->method('generateRefreshToken')->with(1)->willReturn('new_refresh_token');
        $this->jwtService->expects($this->once())->method('getAccessTokenExpire')->willReturn(3600);

        $expectedResponseData = [
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Token refreshed successfully'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($expectedResponseData, 'Token refreshed successfully')
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Token refreshed successfully'
        ]), (string)$response->getBody());
    }

    public function testRefreshWithMissingToken(): void
    {
        $requestBody = []; // Missing refresh_token
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        // Expect validateToken to throw AuthenticationException for missing token
        $this->jwtService->expects($this->once())
            ->method('validateToken')
            ->with('')
            ->willThrowException(new \App\Domain\Exception\AuthenticationException('Invalid token', 401));

        $mockedResponse = (new ResponseFactory())->createResponse(401);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Invalid token' // Expected message from AuthController's catch block
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Invalid token', 401) // Expect the message from the exception
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Invalid token'
        ]), (string)$response->getBody());
    }

    public function testRefreshWithInvalidJwtToken(): void
    {
        $refreshToken = 'invalid.jwt.token';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        // Expect validateToken to throw AuthenticationException for an invalid JWT
        $this->jwtService->expects($this->once())
            ->method('validateToken')
            ->with($refreshToken)
            ->willThrowException(new \App\Domain\Exception\AuthenticationException('Malformed JWT', 401));

        $mockedResponse = (new ResponseFactory())->createResponse(401);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Malformed JWT'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Malformed JWT', 401)
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Malformed JWT'
        ]), (string)$response->getBody());
    }

    public function testRefreshWithWrongTokenType(): void
    {
        $refreshToken = 'valid.access.token';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $decodedToken = (object)['type' => 'access', 'jti' => 'jwt_id_123', 'sub' => 1]; // Wrong type
        $this->jwtService->expects($this->once())->method('validateToken')->with($refreshToken)->willReturn($decodedToken);

        $mockedResponse = (new ResponseFactory())->createResponse(401);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Invalid refresh token'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Invalid refresh token', 401)
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Invalid refresh token'
        ]), (string)$response->getBody());
    }

    public function testRefreshWithRevokedToken(): void
    {
        $refreshToken = 'valid.but.revoked.token';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $decodedToken = (object)['type' => 'refresh', 'jti' => 'jwt_id_revoked', 'sub' => 1];
        $this->jwtService->expects($this->once())->method('validateToken')->with($refreshToken)->willReturn($decodedToken);
        $this->jwtService->expects($this->once())->method('isRefreshTokenValid')->with('jwt_id_revoked')->willReturn(false);

        $mockedResponse = (new ResponseFactory())->createResponse(401);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Refresh token has been revoked'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Refresh token has been revoked', 401)
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Refresh token has been revoked'
        ]), (string)$response->getBody());
    }

    public function testRefreshUserNotFound(): void
    {
        $refreshToken = 'valid.token.user.not.found';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $decodedToken = (object)['type' => 'refresh', 'jti' => 'jwt_id_valid', 'sub' => 999]; // User ID 999 (not found)
        $this->jwtService->expects($this->once())->method('validateToken')->with($refreshToken)->willReturn($decodedToken);
        $this->jwtService->expects($this->once())->method('isRefreshTokenValid')->with('jwt_id_valid')->willReturn(true);
        $this->userRepository->expects($this->once())->method('findById')->with(999)->willReturn(null);

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Usuário não encontrado..'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Usuário não encontrado..', 404)
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Usuário não encontrado..'
        ]), (string)$response->getBody());
    }

    public function testRefreshGenericError(): void
    {
        $refreshToken = 'valid_token_but_generic_error';
        $requestBody = ['refresh_token' => $refreshToken];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $decodedToken = (object)['type' => 'refresh', 'jti' => 'jwt_id_generic', 'sub' => 1];
        $this->jwtService->expects($this->once())->method('validateToken')->with($refreshToken)->willReturn($decodedToken);
        $this->jwtService->expects($this->once())->method('isRefreshTokenValid')->with('jwt_id_generic')->willReturn(true);

        $userMock = $this->createMock(\App\Domain\Entity\User::class);
        $personMock = $this->createMock(\App\Domain\Entity\Person::class);
        $personMock->method('getEmail')->willReturn('test@example.com');
        $userMock->method('getId')->willReturn(1);
        $userMock->method('getPerson')->willReturn($personMock);

        $this->userRepository->expects($this->once())->method('findById')->with(1)->willReturn($userMock);
        
        // Simulate a generic exception during the process (e.g., during revokeRefreshToken)
        $this->jwtService->expects($this->once())
            ->method('revokeRefreshToken')
            ->willThrowException(new \Exception('Database connection lost.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->refresh($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testLogoutSuccess(): void
    {
        $jti = 'jwt_unique_id';
        $exp = time() + 3600; // Example expiration time
        
        $request = $this->createMock(Request::class);
        $request->method('getAttribute')
            ->willReturnMap([
                ['token_jti', null, $jti],
                ['token_exp', null, $exp],
            ]);

        $this->jwtService->expects($this->once())->method('blockToken')->with($jti, $exp);

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Logout successful'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(null, 'Logout successful')
            ->willReturn($mockedResponse);

        $response = $this->authController->logout($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Logout successful'
        ]), (string)$response->getBody());
    }

    public function testLogoutGenericError(): void
    {
        $jti = 'jwt_unique_id';
        $exp = time() + 3600; // Example expiration time

        $request = $this->createMock(Request::class);
        $request->method('getAttribute')
            ->willReturnMap([
                ['token_jti', null, $jti],
                ['token_exp', null, $exp],
            ]);

        // Simulate a generic exception during blockToken
        $this->jwtService->expects($this->once())
            ->method('blockToken')
            ->willThrowException(new \Exception('Database error during token block.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->logout($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testForgotPasswordSuccess(): void
    {
        $requestBody = ['email' => 'user@example.com'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $forgotPasswordRequestDto = new \App\Application\DTO\ForgotPasswordRequestDTO('user@example.com', '127.0.0.1');
        $this->validationService->expects($this->once())->method('validate')->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class));
        $this->forgotPasswordUseCase->expects($this->once())->method('execute')->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class));

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'If this email exists, a password reset email has been sent.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(null, 'If this email exists, a password reset email has been sent.')
            ->willReturn($mockedResponse);

        $response = $this->authController->forgotPassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'If this email exists, a password reset email has been sent.'
        ]), (string)$response->getBody());
    }

    public function testForgotPasswordValidationException(): void
    {
        $requestBody = ['email' => 'invalid-email'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $forgotPasswordRequestDto = new \App\Application\DTO\ForgotPasswordRequestDTO('invalid-email', '127.0.0.1');
        $validationErrors = ['O e-mail "{{ value }}" não é um e-mail válido.'];
        $this->validationService->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class))
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
            ->with($validationErrors, 'Validation failed', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->forgotPassword($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testForgotPasswordEmailSendingFailedException(): void
    {
        $requestBody = ['email' => 'user@example.com'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $forgotPasswordRequestDto = new \App\Application\DTO\ForgotPasswordRequestDTO('user@example.com', '127.0.0.1');
        $this->validationService->expects($this->once())->method('validate')->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class));
        $this->forgotPasswordUseCase->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class))
            ->willThrowException(new EmailSendingFailedException('Failed to send password reset email.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Failed to send password reset email. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('Failed to send password reset email. Please try again later.', null, 500)
            ->willReturn($mockedResponse);

        $response = $this->authController->forgotPassword($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Failed to send password reset email. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testForgotPasswordGenericError(): void
    {
        $requestBody = ['email' => 'user@example.com'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $forgotPasswordRequestDto = new \App\Application\DTO\ForgotPasswordRequestDTO('user@example.com', '127.0.0.1');
        $this->validationService->expects($this->once())->method('validate')->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class));
        $this->forgotPasswordUseCase->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(\App\Application\DTO\ForgotPasswordRequestDTO::class))
            ->willThrowException(new \Exception('Something unexpected happened.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->forgotPassword($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testValidateResetCodeSuccess(): void
    {
        $requestBody = ['email' => 'user@example.com', 'code' => '123456']; // Changed to valid 6-digit numeric code
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $validateResetCodeRequestDto = new \App\Application\DTO\ValidateResetCodeRequestDTO('user@example.com', '123456'); // Changed to valid 6-digit numeric code
        $this->validationService->expects($this->once())->method('validate')->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class));

        // Create a mock PasswordResetResponseDTO to be returned by the use case
        $passwordResetResponseDto = new PasswordResetResponseDTO(
            id: 1,
            userId: 1,
            code: '123456', // Changed to valid 6-digit numeric code
            expiresAt: (new \DateTimeImmutable())->modify('+1 hour')->format(\DateTimeImmutable::ATOM),
            usedAt: null,
            ipAddress: '127.0.0.1'
        );
        $this->validateResetCodeUseCase->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class))
            ->willReturn($passwordResetResponseDto);



        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Code is valid'
        ]));
        $this->jsonResponseFactory->expects($this->exactly(1)) // Changed to exactly(1)
            ->method('success')
            ->with(null, 'Code is valid')
            ->willReturn($mockedResponse);

        $response = $this->authController->validateResetCode($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Code is valid'
        ]), (string)$response->getBody());
    }

    public function testValidateResetCodeValidationException(): void
    {
        $requestBody = ['email' => 'invalid-email', 'code' => 'abc']; // Invalid email and code
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $validateResetCodeRequestDto = new \App\Application\DTO\ValidateResetCodeRequestDTO('invalid-email', 'abc');
        $validationErrors = [
            'O e-mail "{{ value }}" não é um e-mail válido.',
            'O código deve ter exatamente 6 dígitos.',
            'O código deve conter apenas dígitos.'
        ];
        $this->validationService->expects($this->exactly(1))
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class))
            ->willThrowException(new ValidationException('Validation failed', $validationErrors));

        $this->logger->expects($this->exactly(1))->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(400);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]));
        $this->jsonResponseFactory->expects($this->exactly(1))
            ->method('fail')
            ->with($validationErrors, 'Validation failed', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->validateResetCode($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testValidateResetCodeNotFoundException(): void
    {
        $requestBody = ['email' => 'user@example.com', 'code' => '123456'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->exactly(1))
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class));

        $this->validateResetCodeUseCase->expects($this->exactly(1))
            ->method('execute')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class))
            ->willThrowException(new \App\Domain\Exception\NotFoundException('E-mail ou código inválido.'));

        $this->logger->expects($this->exactly(1))->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'E-mail ou código inválido.'
        ]));
        $this->jsonResponseFactory->expects($this->exactly(1))
            ->method('fail')
            ->with(null, 'E-mail ou código inválido.', 404)
            ->willReturn($mockedResponse);

        $response = $this->authController->validateResetCode($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'E-mail ou código inválido.'
        ]), (string)$response->getBody());
    }

    public function testValidateResetCodeGenericError(): void
    {
        $requestBody = ['email' => 'user@example.com', 'code' => '123456'];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->exactly(1))
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class));

        $this->validateResetCodeUseCase->expects($this->exactly(1))
            ->method('execute')
            ->with($this->isInstanceOf(\App\Application\DTO\ValidateResetCodeRequestDTO::class))
            ->willThrowException(new \Exception('Something unexpected happened.'));

        $this->logger->expects($this->exactly(1))->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->exactly(1))
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->validateResetCode($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    // Add other test methods for register, login, refresh, logout, forgotPassword, validateResetCode, resetPassword, verifyEmail

    public function testResetPasswordSuccess(): void
    {
        $requestBody = [
            'email' => 'user@example.com',
            'code' => '123456',
            'password' => 'newSecurePassword123'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ResetPasswordRequestDTO::class));

        $passwordResetResponseDto = new PasswordResetResponseDTO(
            id: 1,
            userId: 1,
            code: '123456',
            expiresAt: (new \DateTimeImmutable())->modify('+1 hour')->format(\DateTimeImmutable::ATOM),
            usedAt: null,
            ipAddress: '127.0.0.1'
        );

        $this->validateResetCodeUseCase->expects($this->once())
            ->method('execute')
            ->willReturn($passwordResetResponseDto);

        $this->resetPasswordUseCase->expects($this->once())
            ->method('execute');

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Password reset successfully'
        ]));

        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(null, 'Password reset successfully')
            ->willReturn($mockedResponse);

        $response = $this->authController->resetPassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Password reset successfully'
        ]), (string)$response->getBody());
    }

    public function testResetPasswordNotFoundException(): void
    {
        $requestBody = [
            'email' => 'user@example.com',
            'code' => 'invalidCode',
            'password' => 'newSecurePassword123'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(\App\Application\DTO\ResetPasswordRequestDTO::class));

        $this->validateResetCodeUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \App\Domain\Exception\NotFoundException('E-mail ou código inválido.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'E-mail ou código inválido.'
        ]));

        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'E-mail ou código inválido.', 404)
            ->willReturn($mockedResponse);

        $response = $this->authController->resetPassword($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'E-mail ou código inválido.'
        ]), (string)$response->getBody());
    }

    public function testResetPasswordValidationException(): void
    {
        $requestBody = [
            'email' => 'invalid-email',
            'code' => '123',
            'password' => 'short'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $validationErrors = ['Invalid email', 'Code must be 6 digits', 'Password too short'];
        $this->validationService->expects($this->once())
            ->method('validate')
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
            ->with($validationErrors, 'Validation failed', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->resetPassword($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => $validationErrors,
            'message' => 'Validation failed'
        ]), (string)$response->getBody());
    }

    public function testResetPasswordGenericError(): void
    {
        $requestBody = [
            'email' => 'user@example.com',
            'code' => '123456',
            'password' => 'newSecurePassword123'
        ];
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($requestBody);

        $this->validationService->expects($this->once())
            ->method('validate')
            ->willThrowException(new \Exception('Generic error'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));

        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('An unexpected error occurred. Please try again later.', null, 500)
            ->willReturn($mockedResponse);

        $response = $this->authController->resetPassword($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailSuccessAlreadyVerified(): void
    {
        $token = 'valid_verification_token';
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['token' => $token]);

        $tokenData = [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
        $verifyEmailResponseDto = new \App\Application\DTO\VerifyEmailResponseDTO($tokenData, true); // Already verified

        $this->verifyEmailUseCase->expects($this->once())
            ->method('execute')
            ->with($token)
            ->willReturn($verifyEmailResponseDto);

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $tokenData,
            'message' => 'Email already verified.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($tokenData, 'Email already verified.')
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $tokenData,
            'message' => 'Email already verified.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailSuccessNewlyVerified(): void
    {
        $token = 'valid_verification_token';
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['token' => $token]);

        $tokenData = [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
        $verifyEmailResponseDto = new \App\Application\DTO\VerifyEmailResponseDTO($tokenData, false); // Newly verified

        $this->verifyEmailUseCase->expects($this->once())
            ->method('execute')
            ->with($token)
            ->willReturn($verifyEmailResponseDto);

        $mockedResponse = (new ResponseFactory())->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $tokenData,
            'message' => 'Email verified successfully.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($tokenData, 'Email verified successfully.')
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $tokenData,
            'message' => 'Email verified successfully.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailMissingToken(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([]); // No token in query params

        $mockedResponse = (new ResponseFactory())->createResponse(400);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Verification token is missing.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Verification token is missing.', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Verification token is missing.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailNotFoundException(): void
    {
        $token = 'non_existent_token';
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['token' => $token]);

        $this->verifyEmailUseCase->expects($this->once())
            ->method('execute')
            ->with($token)
            ->willThrowException(new \App\Domain\Exception\NotFoundException('Token de verificação inválido.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Token de verificação inválido.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'Token de verificação inválido.', 404)
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'Token de verificação inválido.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailValidationException(): void
    {
        $token = 'used_or_expired_token';
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['token' => $token]);

        $this->verifyEmailUseCase->expects($this->once())
            ->method('execute')
            ->with($token)
            ->willThrowException(new \App\Domain\Exception\ValidationException('O token de verificação já foi utilizado.'));

        $this->logger->expects($this->once())->method('warning');

        $mockedResponse = (new ResponseFactory())->createResponse(400);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'O token de verificação já foi utilizado.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('fail')
            ->with(null, 'O token de verificação já foi utilizado.', 400)
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'fail',
            'data' => null,
            'message' => 'O token de verificação já foi utilizado.'
        ]), (string)$response->getBody());
    }

    public function testVerifyEmailGenericError(): void
    {
        $token = 'any_token';
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn(['token' => $token]);

        $this->verifyEmailUseCase->expects($this->once())
            ->method('execute')
            ->with($token)
            ->willThrowException(new \Exception('Something unexpected happened.'));

        $this->logger->expects($this->once())->method('error');

        $mockedResponse = (new ResponseFactory())->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]));
        $this->jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with(
                'An unexpected error occurred. Please try again later.',
                null,
                500
            )
            ->willReturn($mockedResponse);

        $response = $this->authController->verifyEmail($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]), (string)$response->getBody());
    }
}