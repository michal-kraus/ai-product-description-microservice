<?php

declare(strict_types=1);

namespace App\Controller;

use App\AI\Client\AIClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly CacheItemPoolInterface $messengerJobsCache,
        private readonly AIClientInterface $aiClient,
        private readonly string $aiProvider,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }

    #[Route('/ready', name: 'app_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $checks = [
            'redis' => $this->checkRedis(),
            'ai_provider' => $this->checkAiProvider(),
        ];

        $healthy = !\in_array(false, array_column($checks, 'healthy'), true);

        return $this->json([
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
            $this->logger->error('Readiness check failed for Redis.', ['exception' => $e]);

            return ['healthy' => false, 'details' => 'unavailable'];
        }
    }

    /**
     * @return array{healthy: bool, details: string}
     */
    private function checkAiProvider(): array
    {
        try {
            $this->aiClient->ping();

            return ['healthy' => true, 'details' => \sprintf('provider: %s', $this->aiProvider)];
        } catch (Throwable $e) {
            $this->logger->error('Readiness check failed for AI provider.', [
                'provider' => $this->aiProvider,
                'exception' => $e,
            ]);

            return ['healthy' => false, 'details' => \sprintf('provider: %s (unavailable)', $this->aiProvider)];
        }
    }
}
