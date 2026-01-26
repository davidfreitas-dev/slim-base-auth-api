<?php

declare(strict_types=1);

namespace App\Presentation\Api\V1\Controller;

use App\Infrastructure\Http\Response\JsonResponseFactory;
use Exception;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Redis;

class HealthController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Redis $redis,
        private readonly JsonResponseFactory $jsonResponseFactory,
    ) {
    }

    public function check(Request $request): Response
    {
        $health = [
            'status' => 'ok',
            'timestamp' => \date('Y-m-d H:i:s'),
            'services' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
            ],
        ];

        $statusCode = 200;

        foreach ($health['services'] as $service) {
            if ($service['status'] !== 'ok') {
                $health['status'] = 'degraded';
                $statusCode = 503;

                break;
            }
        }

        return $this->jsonResponseFactory->success($health, null, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            $this->pdo->query('SELECT 1');

            return ['status' => 'ok'];
        } catch (Exception $exception) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $this->redis->ping();

            return ['status' => 'ok'];
        } catch (Exception $exception) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
