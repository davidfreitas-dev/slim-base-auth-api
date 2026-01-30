<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exception\AuthenticationException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Infrastructure\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '' || $authHeader === '0' || !\preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->jsonResponseFactory->fail(null, 'Cabeçalho de Autorização inválido', 401);
        }

        try {
            $token = $matches[1];
            $decoded = $this->jwtService->validateToken($token);

            if ($decoded->type !== 'access') {
                throw new AuthenticationException('Tipo de token inválido');
            }

            // Verify if the user still exists and is active
            $user = $this->userRepository->findById((int)$decoded->sub);
            if (!$user instanceof \App\Domain\Entity\User) {
                throw new AuthenticationException('Usuário associado ao token não encontrado ou inativo');
            }

            // Add decoded token data to request attributes
            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('user_email', $decoded->email);
            $request = $request->withAttribute('user_role', $decoded->role); // Added user_role
            $request = $request->withAttribute('token_jti', $decoded->jti);
            $request = $request->withAttribute('token_exp', $decoded->exp);
        } catch (AuthenticationException $authenticationException) {
            return $this->jsonResponseFactory->fail(null, $authenticationException->getMessage(), 401);
        }

        return $handler->handle($request);
    }
}
