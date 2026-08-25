<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\AI\Client\AIClientInterface;
use App\Controller\HealthCheckController;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class HealthCheckControllerTest extends WebTestCase
{
    public function testItReturnsHealthyStatus(): void
    {
        $client = static::createClient();

        $aiClient = $this->createStub(AIClientInterface::class);
        $aiClient->method('ping')->willReturn(true);
        static::getContainer()->set(AIClientInterface::class, $aiClient);

        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $client->request('GET', $router->generate('app_health'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('healthy', $response['status']);
        self::assertArrayHasKey('checks', $response);
        self::assertTrue($response['checks']['redis']['healthy']);
        self::assertSame('connected', $response['checks']['redis']['details']);
        self::assertTrue($response['checks']['ai_provider']['healthy']);
        self::assertSame('provider: ollama', $response['checks']['ai_provider']['details']);
    }

    public function testItReturnsDegradedStatusWhenRedisFails(): void
    {
        $failingCache = $this->createStub(CacheItemPoolInterface::class);
        $failingCache->method('getItem')
            ->willThrowException(new RuntimeException('Redis connection refused'));

        $aiClient = $this->createStub(AIClientInterface::class);
        $aiClient->method('ping')->willReturn(true);

        $controller = new HealthCheckController($failingCache, $aiClient, 'ollama');
        $response = $controller();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('degraded', $data['status']);
        self::assertFalse($data['checks']['redis']['healthy']);
        self::assertSame('Redis connection refused', $data['checks']['redis']['details']);
        self::assertTrue($data['checks']['ai_provider']['healthy']);
    }

    public function testItReturnsDegradedStatusWhenAiProviderFails(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $failingAiClient = $this->createStub(AIClientInterface::class);
        $failingAiClient->method('ping')
            ->willThrowException(new RuntimeException('Connection timeout'));

        $controller = new HealthCheckController($cache, $failingAiClient, 'ollama');
        $response = $controller();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('degraded', $data['status']);
        self::assertTrue($data['checks']['redis']['healthy']);
        self::assertFalse($data['checks']['ai_provider']['healthy']);
        self::assertStringContainsString('Connection timeout', $data['checks']['ai_provider']['details']);
    }
}
