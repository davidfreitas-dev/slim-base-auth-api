<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Application\UseCase\GetErrorLogDetailsUseCase;
use App\Application\UseCase\ListErrorLogsUseCase;
use App\Application\UseCase\ResolveErrorLogUseCase;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ErrorLogController
{
    public function __construct(
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly ListErrorLogsUseCase $listErrorLogsUseCase,
        private readonly GetErrorLogDetailsUseCase $getErrorLogDetailsUseCase,
        private readonly ResolveErrorLogUseCase $resolveErrorLogUseCase,
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
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $perPage = (int)($queryParams['per_page'] ?? 20);
        $severity = $queryParams['severity'] ?? null;
        $resolved = isset($queryParams['resolved']) ? \filter_var($queryParams['resolved'], FILTER_VALIDATE_BOOLEAN) : null;

        $errors = $this->listErrorLogsUseCase->execute($page, $perPage, $severity, $resolved);

        return $this->jsonResponseFactory->success(
            $errors,
            'Error logs retrieved successfully',
            200,
        );
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
        $errorLogId = (int)$args['id'];
        $errorLog = $this->getErrorLogDetailsUseCase->execute($errorLogId);

        if (!$errorLog) {
            return $this->jsonResponseFactory->error(
                'Error log not found',
                null,
                404,
            );
        }

        return $this->jsonResponseFactory->success(
            $errorLog,
            'Error log details retrieved successfully',
            200,
        );
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
        $errorLogId = (int)$args['id'];

        $resolvedByUserId = $request->getAttribute('user_id');

        $success = $this->resolveErrorLogUseCase->execute($errorLogId, $resolvedByUserId);

        if (!$success) {
            return $this->jsonResponseFactory->error(
                'Error log not found or could not be resolved',
                null,
                404,
            );
        }

        return $this->jsonResponseFactory->success(
            null,
            'Error log resolved successfully',
            200,
        );
    }
}
