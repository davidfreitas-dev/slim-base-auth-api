<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Application\DTO\ForgotPasswordRequestDTO;
use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\RegisterResponseDTO;
use App\Application\DTO\RegisterUserRequestDTO;
use App\Application\DTO\ResetPasswordRequestDTO;
use App\Application\DTO\ValidateResetCodeRequestDTO;
use App\Application\Exception\EmailSendingFailedException;
use App\Application\Service\ValidationService;
use App\Application\UseCase\ForgotPasswordUseCase;
use App\Application\UseCase\LoginUseCase;
use App\Application\UseCase\RegisterUserUseCase;
use App\Application\UseCase\ResetPasswordUseCase;
use App\Application\UseCase\ValidateResetCodeUseCase;
use App\Application\UseCase\VerifyEmailUseCase;
use App\Domain\Enum\JsonResponseKey;
use App\Domain\Enum\JwtTokenType;
use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Infrastructure\Security\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Throwable;

class AuthController
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUseCase,
        private readonly LoginUseCase $loginUseCase,
        private readonly ForgotPasswordUseCase $forgotPasswordUseCase,
        private readonly ResetPasswordUseCase $resetPasswordUseCase,
        private readonly ValidateResetCodeUseCase $validateResetCodeUseCase,
        private readonly VerifyEmailUseCase $verifyEmailUseCase,
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
        private readonly LoggerInterface $logger,
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly ValidationService $validationService,
    ) {
    }

    public function register(Request $request): Response
    {
        try {
            $data = $request->getParsedBody();
            $dto = RegisterUserRequestDTO::fromArray($data);
            $this->validationService->validate($dto);

            $userResponseDto = $this->registerUseCase->execute($dto); // Changed variable name

            $accessToken = $this->jwtService->generateAccessToken($userResponseDto->id, $userResponseDto->email);
            $refreshToken = $this->jwtService->generateRefreshToken($userResponseDto->id);
            $expiresIn = $this->jwtService->getAccessTokenExpire();

            $registerResponseDto = new RegisterResponseDTO(
                userId: $userResponseDto->id,
                userName: $userResponseDto->name,
                userEmail: $userResponseDto->email,
                userRoleName: $userResponseDto->roleName,
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                tokenType: 'Bearer',
                expiresIn: $expiresIn,
            );

            $responseData = [
                JsonResponseKey::ACCESS_TOKEN->value => $registerResponseDto->accessToken,
                JsonResponseKey::REFRESH_TOKEN->value => $registerResponseDto->refreshToken,
                JsonResponseKey::TOKEN_TYPE->value => $registerResponseDto->tokenType,
                JsonResponseKey::EXPIRES_IN->value => $registerResponseDto->expiresIn,
            ];

            return $this->jsonResponseFactory->success(
                $responseData,
                'Usuário registrado e logado com sucesso. Por favor, verifique seu e-mail para confirmar sua conta.',
                201,
            );
        } catch (ConflictException $e) {
            $this->logger->warning('User registration conflict', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 409);
        } catch (ValidationException $e) {
            $this->logger->warning('User registration validation failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (EmailSendingFailedException $e) {
            $this->logger->error('Failed to send welcome email', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Usuário registrado, mas falha ao enviar e-mail de boas-vindas.',
                null,
                500,
            );
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during user registration', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function login(Request $request): Response
    {
        try {
            $dto = LoginRequestDTO::fromArray($request->getParsedBody());
            $this->validationService->validate($dto);
            $loginResponseDto = $this->loginUseCase->execute($dto);

            $responseData = [
                JsonResponseKey::ACCESS_TOKEN->value => $loginResponseDto->accessToken,
                JsonResponseKey::REFRESH_TOKEN->value => $loginResponseDto->refreshToken,
                JsonResponseKey::TOKEN_TYPE->value => $loginResponseDto->tokenType,
                JsonResponseKey::EXPIRES_IN->value => $loginResponseDto->expiresIn,
            ];

            return $this->jsonResponseFactory->success($responseData, 'Login bem-sucedido');
        } catch (ValidationException $e) {
            $this->logger->warning('User login validation failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (AuthenticationException $e) {
            $this->logger->warning('User login authentication failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 401);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during user login', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function refresh(Request $request): Response
    {
        try {
            $data = $request->getParsedBody();
            $refreshToken = $data[JsonResponseKey::REFRESH_TOKEN->value] ?? '';
            $decoded = $this->jwtService->validateToken($refreshToken);

            if (JwtTokenType::REFRESH->value !== $decoded->type) {
                return $this->jsonResponseFactory->fail(null, 'Token de atualização inválido', 401);
            }

            if (!$this->jwtService->isRefreshTokenValid($decoded->jti)) {
                return $this->jsonResponseFactory->fail(null, 'Token de atualização foi revogado.', 401);
            }

            $user = $this->userRepository->findById((int)$decoded->sub);
            if (!$user instanceof \App\Domain\Entity\User) {
                return $this->jsonResponseFactory->fail(null, 'Usuário não encontrado.', 404);
            }

            // Invalidate the old refresh token
            $this->jwtService->revokeRefreshToken($decoded->jti);

            // Generate new access and refresh tokens
            $newAccessToken = $this->jwtService->generateAccessToken($user->getId(), $user->getPerson()->getEmail());
            $newRefreshToken = $this->jwtService->generateRefreshToken($user->getId());

            $tokenData = [
                JsonResponseKey::ACCESS_TOKEN->value => $newAccessToken,
                JsonResponseKey::REFRESH_TOKEN->value => $newRefreshToken,
                JsonResponseKey::TOKEN_TYPE->value => 'Bearer',
                JsonResponseKey::EXPIRES_IN->value => $this->jwtService->getAccessTokenExpire(),
            ];

            return $this->jsonResponseFactory->success($tokenData, 'Token atualizado com sucesso');
        } catch (\App\Domain\Exception\AuthenticationException $e) {
            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 401);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during token refresh', ['exception' => $e]);
            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function logout(Request $request): Response
    {
        try {
            $jti = $request->getAttribute('token_jti');
            $exp = $request->getAttribute('token_exp');
            $this->jwtService->blockToken($jti, $exp);

            return $this->jsonResponseFactory->success(null, 'Logout bem-sucedido');
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during logout', ['exception' => $e]);
            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function forgotPassword(Request $request): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? 'UNKNOWN';

        try {
            $forgotPasswordRequest = new ForgotPasswordRequestDTO($email, $ipAddress);
            $this->validationService->validate($forgotPasswordRequest);
            $this->forgotPasswordUseCase->execute($forgotPasswordRequest);

            return $this->jsonResponseFactory->success(
                null,
                'Se este e-mail existir, um e-mail de redefinição de senha foi enviado.',
            );
        } catch (EmailSendingFailedException $e) {
            $this->logger->error('Failed to send password reset email', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Falha ao enviar e-mail de redefinição de senha. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        } catch (ValidationException $e) {
            $this->logger->warning('Forgot password validation failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during password reset', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function validateResetCode(Request $request): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $code = $data['code'] ?? '';

        try {
            $validateRequest = new ValidateResetCodeRequestDTO($email, $code);
            $this->validationService->validate($validateRequest);
            $this->validateResetCodeUseCase->execute($validateRequest);
            return $this->jsonResponseFactory->success(null, 'Código é válido');
        } catch (NotFoundException $e) {
            $this->logger->warning('Invalid reset code validation attempt', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->logger->warning('Invalid reset code input', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during code validation', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function resetPassword(Request $request): Response
    {
        try {
            $resetPasswordDto = ResetPasswordRequestDTO::fromArray($request->getParsedBody());
            $this->validationService->validate($resetPasswordDto);

            $validateRequest = new ValidateResetCodeRequestDTO(
                $resetPasswordDto->email,
                $resetPasswordDto->code,
            );

            $passwordResetResponseDto = $this->validateResetCodeUseCase->execute($validateRequest);

            $this->resetPasswordUseCase->execute($passwordResetResponseDto, $resetPasswordDto);

            return $this->jsonResponseFactory->success(null, 'Senha redefinida com sucesso');
        } catch (NotFoundException $e) {
            $this->logger->warning('Password reset failed due to invalid code', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->logger->warning('Password reset failed due to validation error', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during password reset', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }

    public function verifyEmail(Request $request): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';

        if (empty($token)) {
            return $this->jsonResponseFactory->fail(null, 'Token de verificação está faltando.', 400);
        }

        try {
            $result = $this->verifyEmailUseCase->execute($token);

            $message = $result->wasAlreadyVerified()
                ? 'E-mail já verificado.'
                : 'E-mail verificado com sucesso.';

            return $this->jsonResponseFactory->success(
                $result->getTokenData(),
                $message,
            );
        } catch (NotFoundException $e) {
            $this->logger->warning('Email verification failed: ' . $e->getMessage(), ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->logger->warning('Email verification failed: ' . $e->getMessage(), ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during email verification', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
                null,
                500,
            );
        }
    }
}
