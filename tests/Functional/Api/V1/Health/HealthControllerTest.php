<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Health;

use Fig\Http\Message\StatusCodeInterface;
use Tests\Functional\FunctionalTestCase;

class HealthControllerTest extends FunctionalTestCase
{
    public function testHealthCheckReturnsOkAndCorrectStructure(): void
    {
        // Act
        $response = $this->sendRequest('GET', '/api/v1/health');

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('status', $body['data']);
        $this->assertArrayHasKey('timestamp', $body['data']);
        $this->assertArrayHasKey('services', $body['data']);
        $this->assertEquals('ok', $body['data']['status']);

        // Check services
        $this->assertArrayHasKey('database', $body['data']['services']);
        $this->assertEquals('ok', $body['data']['services']['database']['status']);
        $this->assertArrayHasKey('redis', $body['data']['services']);
        $this->assertEquals('ok', $body['data']['services']['redis']['status']);
    }
}
