<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use App\DTO\GenerateProductDescriptionRequest;
use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Service\JobStatusManager;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class ProductDescriptionControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private RouterInterface $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $this->router = $router;

        $rateLimiterCache = static::getContainer()->get('rate_limiter.cache');
        if ($rateLimiterCache instanceof CacheItemPoolInterface) {
            $rateLimiterCache->clear();
        }
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $aiClientMock = $this->createMock(AIClientInterface::class);
        $aiClientMock->expects($this->once())
            ->method('generateDescription')
            ->with(new DescriptionRequest($expectedName, $expectedFeatures))
            ->willReturn(new AIResponse($mockedOutput));

        static::getContainer()->set(AIClientInterface::class, $aiClientMock);

        $payload = [
            'name' => $expectedName,
            'features' => $expectedFeatures,
        ];
        $this->client->request('POST', $this->router->generate('app_product_descriptions_sync'), $payload);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($mockedOutput, $responseData['description']);
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItDispatchesAsyncJobAndReturnsAccepted(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $this->client->request('POST', $this->router->generate('app_product_descriptions_async'), [
            'name' => $expectedName,
            'features' => $expectedFeatures,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('job_id', $response);
        self::assertSame(GenerateProductDescriptionMessageStatus::PENDING->value, $response['status']);
    }

    public function testItReturns404ForNonExistentJob(): void
    {
        $this->client->request(
            'GET',
            $this->router->generate('app_product_descriptions_async_status', ['jobId' => 'non-existent-job-id']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItReturnsJobStatusAndDescriptionWhenReady(): void
    {
        $jobStatusManager = static::getContainer()->get(JobStatusManager::class);
        self::assertInstanceOf(JobStatusManager::class, $jobStatusManager);

        $jobId = 'test-job-999';
        $jobStatusManager->createJob($jobId);
        $jobStatusManager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
            'description' => 'Premium mechanical keyboard with RGB backlighting...',
        ]);

        $url = $this->router->generate('app_product_descriptions_async_status', [
            'jobId' => $jobId,
        ]);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('test-job-999', $response['job_id']);
        self::assertSame(GenerateProductDescriptionMessageStatus::COMPLETED->value, $response['status']);
        self::assertSame('Premium mechanical keyboard with RGB backlighting...', $response['description']);
    }

    public function testItReturns400WhenParametersAreMissingInSync(): void
    {
        $syncUrl = $this->router->generate('app_product_descriptions_sync');

        $this->client->request(
            'POST',
            $syncUrl,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: (string) json_encode([]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($response);
    }

    public function testItReturns400WhenParametersAreMissingInAsync(): void
    {
        $asyncUrl = $this->router->generate('app_product_descriptions_async');

        $this->client->request(
            'POST',
            $asyncUrl,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: (string) json_encode([]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($response);
    }

    public function testItReturns400WhenNameExceedsMaxLength(): void
    {
        $longName = str_repeat('a', GenerateProductDescriptionRequest::MAX_NAME_LENGTH + 1);
        $this->client->request(
            'POST',
            $this->router->generate('app_product_descriptions_sync'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: (string) json_encode([
                'name' => $longName,
                'features' => 'Valid features',
            ]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('too long', $content);
    }

    public function testItReturns400WhenFeaturesExceedMaxLength(): void
    {
        $longFeatures = str_repeat('f', GenerateProductDescriptionRequest::MAX_FEATURES_LENGTH + 1);
        $this->client->request(
            'POST',
            $this->router->generate('app_product_descriptions_async'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: (string) json_encode([
                'name' => 'Valid name',
                'features' => $longFeatures,
            ]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('too long', $content);
    }

    public function testItReturns429WhenRateLimitIsExceeded(): void
    {
        // Prevent kernel reboot so rate limiter cache persists between requests
        $this->client->disableReboot();

        $aiClientMock = $this->createStub(AIClientInterface::class);
        $aiClientMock->method('generateDescription')
            ->willReturn(new AIResponse('Description'));
        static::getContainer()->set(AIClientInterface::class, $aiClientMock);

        $syncUrl = $this->router->generate('app_product_descriptions_sync');

        for ($i = 0; $i < 10; $i++) {
            $this->client->request('POST', $syncUrl, [
                'name' => 'Product',
                'features' => 'Features',
            ]);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('POST', $syncUrl, [
            'name' => 'Product',
            'features' => 'Features',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertStringContainsString('Too many requests', $response['error']);
    }

    public function testItReturns429WhenStatusRateLimitIsExceeded(): void
    {
        $this->client->disableReboot();

        $url = $this->router->generate('app_product_descriptions_async_status', ['jobId' => 'job-status-limit']);

        for ($i = 0; $i < 20; $i++) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertStringContainsString('Too many status check requests', $response['error']);
    }

    public function testItReturns500WhenGeneratorFailsInSync(): void
    {
        $aiClientMock = $this->createStub(AIClientInterface::class);
        $aiClientMock->method('generateDescription')
            ->willThrowException(new RuntimeException('Connection timeout'));
        static::getContainer()->set(AIClientInterface::class, $aiClientMock);

        $this->client->request('POST', $this->router->generate('app_product_descriptions_sync'), [
            'name' => 'Failing Product',
            'features' => 'Some features',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Failed to generate product description.', $response['error']);
        self::assertArrayHasKey('details', $response);
    }
}
