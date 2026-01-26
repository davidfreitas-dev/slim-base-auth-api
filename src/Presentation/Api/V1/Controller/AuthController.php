<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Application\DTO\ForgotPasswordRequestDTO;
use App\Application\DTO\LoginRequestDTO;
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
        $data = $request->getParsedBody();
        $dto = RegisterUserRequestDTO::fromArray($data);
        $this->validationService->validate($dto);

        try {
            $user = $this->registerUseCase->execute($dto);

            $accessToken = $this->jwtService->generateAccessToken($user->getId(), $user->getEmail());
            $refreshToken = $this->jwtService->generateRefreshToken($user->getId());

            $responseData = [
                JsonResponseKey::ACCESS_TOKEN->value => $accessToken,
                JsonResponseKey::REFRESH_TOKEN->value => $refreshToken,
                JsonResponseKey::TOKEN_TYPE->value => 'Bearer',
                JsonResponseKey::EXPIRES_IN->value => $this->jwtService->getAccessTokenExpire(),
            ];

            return $this->jsonResponseFactory->success(
                $responseData,
                'User registered and logged in successfully. Please check your email to verify your account.',
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
                'User registered, but failed to send welcome email.',
                null,
                500,
            );
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during user registration', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'An unexpected error occurred. Please try again later.',
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
            $result = $this->loginUseCase->execute($dto);

            return $this->jsonResponseFactory->success($result, 'Login successful');
        } catch (ValidationException $e) {
            $this->logger->warning('User login validation failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (AuthenticationException $e) {
            $this->logger->warning('User login authentication failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 401);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during user login', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'An unexpected error occurred. Please try again later.',
                null,
                500,
            );
        }
    }

    public function refresh(Request $request): Response
    {
        $data = $request->getParsedBody();
        $refreshToken = $data[JsonResponseKey::REFRESH_TOKEN->value] ?? '';
        $decoded = $this->jwtService->validateToken($refreshToken);

        if (!$decoded || JwtTokenType::REFRESH->value !== $decoded->type) {
            return $this->jsonResponseFactory->fail(null, 'Invalid refresh token', 401);
        }

        if (!$this->jwtService->isRefreshTokenValid($decoded->jti)) {
            return $this->jsonResponseFactory->fail(null, 'Refresh token has been revoked', 401);
        }

        $user = $this->userRepository->findById((int)$decoded->sub);
        if (!$user instanceof \App\Domain\Entity\User) {
            return $this->jsonResponseFactory->fail(null, 'User not found', 404);
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

        return $this->jsonResponseFactory->success($tokenData, 'Token refreshed successfully');
    }

    public function logout(Request $request): Response
    {
        $jti = $request->getAttribute('token_jti');
        $exp = $request->getAttribute('token_exp');
        $this->jwtService->blockToken($jti, $exp);

        return $this->jsonResponseFactory->success(null, 'Logout successful');
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
                'If this email exists, a password reset email has been sent.',
            );
        } catch (EmailSendingFailedException $e) {
            $this->logger->error('Failed to send password reset email', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'Failed to send password reset email. Please try again later.',
                null,
                500,
            );
        } catch (ValidationException $e) {
            $this->logger->warning('Forgot password validation failed', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during password reset', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'An unexpected error occurred. Please try again later.',
                null,
                500,
            );
        }
    }

    public function validateResetToken(Request $request): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $token = $data['token'] ?? '';

        try {
            $validateRequest = new ValidateResetCodeRequestDTO($email, $token);
            $this->validationService->validate($validateRequest);
            $this->validateResetCodeUseCase->execute($validateRequest);

            return $this->jsonResponseFactory->success(null, 'Code is valid');
        } catch (NotFoundException $e) {
            $this->logger->warning('Invalid reset code validation attempt', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->logger->warning('Invalid reset code input', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during code validation', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'An unexpected error occurred. Please try again later.',
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

            $passwordReset = $this->validateResetCodeUseCase->execute($validateRequest);

            $this->resetPasswordUseCase->execute($passwordReset, $resetPasswordDto);

            return $this->jsonResponseFactory->success(null, 'Password reset successfully');
        } catch (NotFoundException $e) {
            $this->logger->warning('Password reset failed due to invalid code', ['exception' => $e]);

            return $this->jsonResponseFactory->fail(null, $e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->logger->warning('Password reset failed due to validation error', ['exception' => $e]);

            return $this->jsonResponseFactory->fail($e->getErrors(), $e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred during password reset', ['exception' => $e]);

            return $this->jsonResponseFactory->error(
                'An unexpected error occurred. Please try again later.',
                null,
                500,
            );
        }
    }

    public function verifyEmail(Request $request): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';

        if (empty($token)) {
            return $this->jsonResponseFactory->fail(null, 'Verification token is missing.', 400);
        }

        try {
            $result = $this->verifyEmailUseCase->execute($token);

            $message = $result->wasAlreadyVerified()
                ? 'Email already verified.'
                : 'Email verified successfully.';

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
                'An unexpected error occurred. Please try again later.',
                null,
                500,
            );
        }
    }
}
