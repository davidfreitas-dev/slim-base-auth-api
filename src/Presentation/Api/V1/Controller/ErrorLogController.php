<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Application\UseCase\GetErrorLogDetailsUseCase;
use App\Application\UseCase\ListErrorLogsUseCase;
use App\Application\UseCase\ResolveErrorLogUseCase;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Throwable;

class ErrorLogController
{
    public function __construct(
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly ListErrorLogsUseCase $listErrorLogsUseCase,
        private readonly GetErrorLogDetailsUseCase $getErrorLogDetailsUseCase,
        private readonly ResolveErrorLogUseCase $resolveErrorLogUseCase,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Lists error logs.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    public function listErrorLogs(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $page = (int)($queryParams['page'] ?? 1);
            $perPage = (int)($queryParams['per_page'] ?? 20);
            $severity = $queryParams['severity'] ?? null;
            $resolved = isset($queryParams['resolved']) ? \filter_var($queryParams['resolved'], FILTER_VALIDATE_BOOLEAN) : null;

            $errors = $this->listErrorLogsUseCase->execute($page, $perPage, $severity, $resolved);

            return $this->jsonResponseFactory->success(
                $errors,
                'Logs de erro recuperados com sucesso',
                200,
            );
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred while listing error logs', ['exception' => $e]);
            return $this->jsonResponseFactory->error('Ocorreu um erro inesperado.', null, 500);
        }
    }

    /**
     * Gets details of a single error log.
     *
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function getErrorLogDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $errorLogId = (int)$args['id'];
            $errorLog = $this->getErrorLogDetailsUseCase->execute($errorLogId);

            if (!$errorLog) {
                return $this->jsonResponseFactory->error(
                    'Log de erro não encontrado',
                    null,
                    404,
                );
            }

            return $this->jsonResponseFactory->success(
                $errorLog,
                'Detalhes do log de erro recuperados com sucesso',
                200,
            );
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred while getting error log details', ['exception' => $e]);
            return $this->jsonResponseFactory->error('Ocorreu um erro inesperado.', null, 500);
        }
    }

    /**
     * Resolves an error log.
     *
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function resolveErrorLog(Request $request, Response $response, array $args): Response
    {
        try {
            $errorLogId = (int)$args['id'];

            $resolvedByUserId = $request->getAttribute('user_id');

            $success = $this->resolveErrorLogUseCase->execute($errorLogId, $resolvedByUserId);

            if (!$success) {
                return $this->jsonResponseFactory->error(
                    'Log de erro não encontrado ou não pôde ser resolvido',
                    null,
                    404,
                );
            }

            return $this->jsonResponseFactory->success(
                null,
                'Log de erro resolvido com sucesso',
                200,
            );
        } catch (Throwable $e) {
            $this->logger->error('An unexpected error occurred while resolving error log', ['exception' => $e]);
            return $this->jsonResponseFactory->error('Ocorreu um erro inesperado.', null, 500);
        }
    }
}
