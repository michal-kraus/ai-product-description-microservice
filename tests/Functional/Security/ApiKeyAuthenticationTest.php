<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiKeyAuthenticationTest extends WebTestCase
{
    private const string VALID_API_KEY = 'default-test-api-key-12345';
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testProtectedSyncEndpointReturns401WhenNoCredentialsProvided(): void
    {
        $this->client->request('POST', '/product/descriptions/sync', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Laptop', 'features' => '16GB RAM'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testProtectedSyncEndpointReturns401WithInvalidBearerToken(): void
    {
        $this->client->request('POST', '/product/descriptions/sync', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ], content: json_encode(['name' => 'Laptop', 'features' => '16GB RAM'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertTrue($this->client->getResponse()->headers->has('WWW-Authenticate'));
    }

    public function testProtectedSyncEndpointAllowsValidBearerToken(): void
    {
        $this->client->request('POST', '/product/descriptions/sync', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_API_KEY,
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testProtectedAsyncEndpointReturns401WithoutCredentials(): void
    {
        $this->client->request('POST', '/product/descriptions/async', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Laptop', 'features' => '16GB RAM'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testProtectedJobStatusEndpointReturns401WithoutCredentials(): void
    {
        $this->client->request('GET', '/product/descriptions/async/01912ec9-4b8c-7f51-b0e6-123456789abc');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testHealthEndpointIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testReadyEndpointIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/ready');

        // Can be 200 (healthy) or 503 (degraded in test environment), but must not be 401
        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testApiDocsArePubliclyAccessible(): void
    {
        $this->client->request('GET', '/api/docs');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/docs/openapi.yaml');
        self::assertResponseIsSuccessful();
    }

    public function testRateLimitingIsIsolatedPerAuthenticatedClient(): void
    {
        $cache = static::getContainer()->get('rate_limiter.cache');
        if ($cache instanceof CacheItemPoolInterface) {
            $cache->clear();
        }

        $url = '/product/descriptions/async/test-job-isolation';

        for ($i = 0; $i < 20; ++$i) {
            $this->client->request('GET', $url, server: [
                'HTTP_AUTHORIZATION' => 'Bearer api-key-client-a',
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $this->client->request('GET', $url, server: [
            'HTTP_AUTHORIZATION' => 'Bearer api-key-client-a',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);

        $this->client->request('GET', $url, server: [
            'HTTP_AUTHORIZATION' => 'Bearer api-key-client-b',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
