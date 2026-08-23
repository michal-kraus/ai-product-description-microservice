<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Service\JobStatusManager;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
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
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
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
        string $mockedOutput
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
            $this->router->generate('app_product_descriptions_async_status', ['jobId' => 'non-existent-job-id'])
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
            'description' => 'Świetna klawiatura z podświetleniem RGB...',
        ]);

        $url = $this->router->generate('app_product_descriptions_async_status', [
            'jobId' => $jobId,
        ]);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('test-job-999', $response['job_id']);
        self::assertSame(GenerateProductDescriptionMessageStatus::COMPLETED->value, $response['status']);
        self::assertSame('Świetna klawiatura z podświetleniem RGB...', $response['description']);
    }

    public function testItReturns400WhenParametersAreMissingInSync(): void
    {
        $syncUrl = $this->router->generate('app_product_descriptions_sync');

        $this->client->request('POST', $syncUrl, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $response);
    }

    public function testItReturns400WhenParametersAreMissingInAsync(): void
    {
        $asyncUrl = $this->router->generate('app_product_descriptions_async');

        $this->client->request('POST', $asyncUrl, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $response);
    }
}
