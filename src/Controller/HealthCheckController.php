<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HealthCheckController
{
    public function __construct(
        private readonly CacheItemPoolInterface $messengerJobsCache,
        private readonly string $aiProvider,
    ) {}

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'redis' => $this->checkRedis(),
            'ai_provider' => $this->checkAiProvider(),
        ];

        $healthy = !\in_array(false, array_column($checks, 'healthy'), true);

        return new JsonResponse([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * @return array{healthy: bool, details: string}
     */
    private function checkRedis(): array
    {
        try {
            $item = $this->messengerJobsCache->getItem('health_check_ping');
            $item->set('pong');
            $item->expiresAfter(10);
            $this->messengerJobsCache->save($item);

            return ['healthy' => true, 'details' => 'connected'];
        } catch (Throwable $e) {
            return ['healthy' => false, 'details' => $e->getMessage()];
        }
    }

    /**
     * @return array{healthy: bool, details: string}
     */
    private function checkAiProvider(): array
    {
        return ['healthy' => true, 'details' => \sprintf('provider: %s', $this->aiProvider)];
    }
}
