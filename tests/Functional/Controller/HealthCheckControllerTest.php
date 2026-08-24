<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class HealthCheckControllerTest extends WebTestCase
{
    public function testItReturnsHealthyStatus(): void
    {
        $client = static::createClient();
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
    }

    public function testItReturnsDegradedStatusWhenRedisFails(): void
    {
        $failingCache = $this->createStub(\Psr\Cache\CacheItemPoolInterface::class);
        $failingCache->method('getItem')
            ->willThrowException(new RuntimeException('Redis connection refused'));

        $controller = new \App\Controller\HealthCheckController($failingCache, 'ollama');
        $response = $controller();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('degraded', $data['status']);
        self::assertFalse($data['checks']['redis']['healthy']);
        self::assertSame('Redis connection refused', $data['checks']['redis']['details']);
    }
}
