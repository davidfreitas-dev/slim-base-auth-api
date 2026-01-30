<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Api\V1\Controller;

use App\Application\DTO\ErrorLogListResponseDTO;
use App\Application\DTO\ErrorLogResponseDTO;
use App\Application\UseCase\GetErrorLogDetailsUseCase;
use App\Application\UseCase\ListErrorLogsUseCase;
use App\Application\UseCase\ResolveErrorLogUseCase;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use App\Presentation\Api\V1\Controller\ErrorLogController;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ErrorLogControllerTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->responseFactory = new ResponseFactory();
    }

    // --- Test for listErrorLogs method ---
    public function testListErrorLogsSuccess(): void
    {
        $page = 1;
        $perPage = 20;
        $severity = 'ERROR';
        $resolved = false;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([
                'page' => (string)$page,
                'per_page' => (string)$perPage,
                'severity' => $severity,
                'resolved' => (string)(int)$resolved,
            ]);

        $createdAt1 = new DateTimeImmutable();
        $errorLogResponseDto1 = new ErrorLogResponseDTO(
            id: 1,
            severity: 'ERROR',
            message: 'Test Error 1',
            context: [],
            resolvedAt: null,
            resolvedByUserId: null,
            createdAt: $createdAt1
        );

        $createdAt2 = new DateTimeImmutable();
        $errorLogResponseDto2 = new ErrorLogResponseDTO(
            id: 2,
            severity: 'WARNING',
            message: 'Test Warning 2',
            context: [],
            resolvedAt: null,
            resolvedByUserId: null,
            createdAt: $createdAt2
        );
        $errorLogs = [$errorLogResponseDto1, $errorLogResponseDto2];
        $total = count($errorLogs);

        $errorLogListResponseDTO = new ErrorLogListResponseDTO($errorLogs, $total, $page, $perPage);

        $listErrorLogsUseCase->expects($this->once())
            ->method('execute')
            ->with($page, $perPage, $severity, $resolved)
            ->willReturn($errorLogListResponseDTO);

        $expectedErrorLogData = [];
        foreach ($errorLogs as $log) {
            $expectedErrorLogData[] = [
                'id' => $log->id,
                'severity' => $log->severity,
                'message' => $log->message,
                'context' => $log->context,
                'created_at' => $log->createdAt->format(DateTimeImmutable::ATOM),
                'resolved_at' => $log->resolvedAt,
                'resolved_by_user_id' => $log->resolvedByUserId,
            ];
        }

        $expectedResponseData = [
            'errors' => $expectedErrorLogData,
            'total' => $errorLogListResponseDTO->total,
            'page' => $errorLogListResponseDTO->page,
            'per_page' => $errorLogListResponseDTO->perPage,
        ];

        $mockedResponse = $this->responseFactory->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Error logs retrieved successfully'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($errorLogListResponseDTO, 'Error logs retrieved successfully', 200)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->listErrorLogs($request, $this->responseFactory->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Error logs retrieved successfully'
        ]), (string)$response->getBody());
    }

    public function testListErrorLogsWithDefaultParams(): void
    {
        $page = 1;
        $perPage = 20;
        $severity = null;
        $resolved = null;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([]); // No query params, use defaults

        $errorLogListResponseDTO = new ErrorLogListResponseDTO([], 0, $page, $perPage);

        $listErrorLogsUseCase->expects($this->once())
            ->method('execute')
            ->with($page, $perPage, $severity, $resolved)
            ->willReturn($errorLogListResponseDTO);

        $expectedResponseData = [
            'errors' => [],
            'total' => $errorLogListResponseDTO->total,
            'page' => $errorLogListResponseDTO->page,
            'per_page' => $errorLogListResponseDTO->perPage,
        ];

        $mockedResponse = $this->responseFactory->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Error logs retrieved successfully'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($errorLogListResponseDTO, 'Error logs retrieved successfully', 200)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->listErrorLogs($request, $this->responseFactory->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedResponseData,
            'message' => 'Error logs retrieved successfully'
        ]), (string)$response->getBody());
    }

    public function testListErrorLogsGenericError(): void
    {
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([]);

        $listErrorLogsUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error.'));

        $mockedResponse = $this->responseFactory->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('An unexpected error occurred.', null, 500)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->listErrorLogs($request, $this->responseFactory->createResponse());

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]), (string)$response->getBody());
    }

    // --- Test for getErrorLogDetails method ---
    public function testGetErrorLogDetailsSuccess(): void
    {
        $errorLogId = 1;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];
        $createdAt = new DateTimeImmutable();

        $errorLogResponseDto = new ErrorLogResponseDTO(
            id: $errorLogId,
            severity: 'ERROR',
            message: 'Details Error',
            context: ['detail' => 'info'],
            resolvedAt: null,
            resolvedByUserId: null,
            createdAt: $createdAt
        );

        $getErrorLogDetailsUseCase->expects($this->once())
            ->method('execute')
            ->with($errorLogId)
            ->willReturn($errorLogResponseDto);

        $expectedErrorLogData = [
            'id' => $errorLogResponseDto->id,
            'severity' => $errorLogResponseDto->severity,
            'message' => $errorLogResponseDto->message,
            'context' => $errorLogResponseDto->context,
            'created_at' => $errorLogResponseDto->createdAt->format(DateTimeImmutable::ATOM),
            'resolved_at' => $errorLogResponseDto->resolvedAt,
            'resolved_by_user_id' => $errorLogResponseDto->resolvedByUserId,
        ];

        $mockedResponse = $this->responseFactory->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $expectedErrorLogData,
            'message' => 'Error log details retrieved successfully'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with($errorLogResponseDto, 'Error log details retrieved successfully', 200)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->getErrorLogDetails($request, $response, $args);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => $expectedErrorLogData,
            'message' => 'Error log details retrieved successfully'
        ]), (string)$response->getBody());
    }

    public function testGetErrorLogDetailsNotFound(): void
    {
        $errorLogId = 99;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];

        $getErrorLogDetailsUseCase->expects($this->once())
            ->method('execute')
            ->with($errorLogId)
            ->willReturn(null);

        $mockedResponse = $this->responseFactory->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Error log not found'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('Error log not found', null, 404)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->getErrorLogDetails($request, $response, $args);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Error log not found'
        ]), (string)$response->getBody());
    }

    public function testGetErrorLogDetailsGenericError(): void
    {
        $errorLogId = 1;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];

        $getErrorLogDetailsUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error.'));

        $mockedResponse = $this->responseFactory->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('An unexpected error occurred.', null, 500)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->getErrorLogDetails($request, $response, $args);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]), (string)$response->getBody());
    }

    // --- Test for resolveErrorLog method ---
    public function testResolveErrorLogSuccess(): void
    {
        $errorLogId = 1;
        $resolvedByUserId = 123;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];
        
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($resolvedByUserId);

        $resolveErrorLogUseCase->expects($this->once())
            ->method('execute')
            ->with($errorLogId, $resolvedByUserId)
            ->willReturn(true);

        $mockedResponse = $this->responseFactory->createResponse(200);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Error log resolved successfully'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('success')
            ->with(null, 'Error log resolved successfully', 200)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->resolveErrorLog($request, $response, $args);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'Error log resolved successfully'
        ]), (string)$response->getBody());
    }

    public function testResolveErrorLogNotFound(): void
    {
        $errorLogId = 99;
        $resolvedByUserId = 123;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];
        
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($resolvedByUserId);

        $resolveErrorLogUseCase->expects($this->once())
            ->method('execute')
            ->with($errorLogId, $resolvedByUserId)
            ->willReturn(false);

        $mockedResponse = $this->responseFactory->createResponse(404);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Error log not found or could not be resolved'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('Error log not found or could not be resolved', null, 404)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->resolveErrorLog($request, $response, $args);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'Error log not found or could not be resolved'
        ]), (string)$response->getBody());
    }

    public function testResolveErrorLogGenericError(): void
    {
        $errorLogId = 1;
        $resolvedByUserId = 123;
        
        $jsonResponseFactory = $this->createMock(JsonResponseFactory::class);
        $listErrorLogsUseCase = $this->createMock(ListErrorLogsUseCase::class);
        $getErrorLogDetailsUseCase = $this->createMock(GetErrorLogDetailsUseCase::class);
        $resolveErrorLogUseCase = $this->createMock(ResolveErrorLogUseCase::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $args = ['id' => (string)$errorLogId];
        
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($resolvedByUserId);

        $resolveErrorLogUseCase->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error.'));

        $mockedResponse = $this->responseFactory->createResponse(500);
        $mockedResponse->getBody()->write(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]));
        
        $jsonResponseFactory->expects($this->once())
            ->method('error')
            ->with('An unexpected error occurred.', null, 500)
            ->willReturn($mockedResponse);

        $errorLogController = new ErrorLogController(
            $jsonResponseFactory,
            $listErrorLogsUseCase,
            $getErrorLogDetailsUseCase,
            $resolveErrorLogUseCase,
            $logger
        );
        
        $response = $errorLogController->resolveErrorLog($request, $response, $args);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => 'error',
            'data' => null,
            'message' => 'An unexpected error occurred.'
        ]), (string)$response->getBody());
    }
}