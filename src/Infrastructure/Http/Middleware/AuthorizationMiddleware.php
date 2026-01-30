<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Response\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedRoles
     */
    public function __construct(
        private readonly array $allowedRoles,
        private readonly JsonResponseFactory $jsonResponseFactory,
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $userRole = $request->getAttribute('user_role'); // This is a string (role name) from JwtAuthMiddleware

        if (!$userRole) {
            return $this->jsonResponseFactory->fail(null, 'Falha na verificação de autorização. Função de usuário não encontrada no token.', 403);
        }

        if ($this->allowedRoles === []) {
            return $this->jsonResponseFactory->fail(null, 'Falha na verificação de autorização. Funções permitidas não configuradas para esta rota.', 500);
        }

        if (!\in_array($userRole, $this->allowedRoles, true)) {
            return $this->jsonResponseFactory->fail(null, 'Proibido: Permissões insuficientes.', 403);
        }

        return $handler->handle($request);
    }
}
