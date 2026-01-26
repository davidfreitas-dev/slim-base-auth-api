<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use App\Domain\Enum\JsonResponseKey;
use App\Domain\Enum\JsonResponseStatus;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;

class JsonResponseFactory
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function success(mixed $data = null, ?string $message = null, int $statusCode = 200): Response
    {
        return $this->buildResponse(JsonResponseStatus::SUCCESS, $data, $message, $statusCode);
    }

    public function fail(mixed $data = null, ?string $message = null, int $statusCode = 400): Response
    {
        return $this->buildResponse(JsonResponseStatus::FAIL, $data, $message, $statusCode);
    }

    public function error(string $message, mixed $data = null, int $statusCode = 500): Response
    {
        return $this->buildResponse(JsonResponseStatus::ERROR, $data, $message, $statusCode);
    }

    private function buildResponse(JsonResponseStatus $status, mixed $data, ?string $message, int $statusCode): Response
    {
        $payload = [JsonResponseKey::STATUS->value => $status->value];

        if ($message) {
            $payload[JsonResponseKey::MESSAGE->value] = $message;
        }

        if ($data) {
            $payload[JsonResponseKey::DATA->value] = $data;
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write(\json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
