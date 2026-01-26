<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Response;

use App\Domain\Enum\JsonResponseKey;
use App\Domain\Enum\JsonResponseStatus;
use App\Infrastructure\Http\Response\JsonResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

#[CoversClass(JsonResponseFactory::class)]
class JsonResponseFactoryTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private JsonResponseFactory $jsonResponseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->jsonResponseFactory = new JsonResponseFactory($this->responseFactory);
    }

    public function testSuccessResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->responseFactory->method('createResponse')->with(200)->willReturn($response);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->with('Content-Type', 'application/json')->willReturn($response);

        $data = ['test' => 'data'];
        $message = 'Success message';

        $payload = [
            JsonResponseKey::STATUS->value => JsonResponseStatus::SUCCESS->value,
            JsonResponseKey::MESSAGE->value => $message,
            JsonResponseKey::DATA->value => $data,
        ];

        $stream->expects($this->once())->method('write')->with(json_encode($payload));

        $this->jsonResponseFactory->success($data, $message);
    }

    public function testFailResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->responseFactory->method('createResponse')->with(400)->willReturn($response);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->with('Content-Type', 'application/json')->willReturn($response);

        $data = ['test' => 'data'];
        $message = 'Fail message';

        $payload = [
            JsonResponseKey::STATUS->value => JsonResponseStatus::FAIL->value,
            JsonResponseKey::MESSAGE->value => $message,
            JsonResponseKey::DATA->value => $data,
        ];

        $stream->expects($this->once())->method('write')->with(json_encode($payload));

        $this->jsonResponseFactory->fail($data, $message);
    }

    public function testErrorResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->responseFactory->method('createResponse')->with(500)->willReturn($response);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->with('Content-Type', 'application/json')->willReturn($response);

        $message = 'Error message';

        $payload = [
            JsonResponseKey::STATUS->value => JsonResponseStatus::ERROR->value,
            JsonResponseKey::MESSAGE->value => $message,
        ];

        $stream->expects($this->once())->method('write')->with(json_encode($payload));

        $this->jsonResponseFactory->error($message);
    }
}
