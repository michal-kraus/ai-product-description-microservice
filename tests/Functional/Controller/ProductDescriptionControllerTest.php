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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProductDescriptionControllerTest extends WebTestCase
{
    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
    ): void {
        $client = static::createClient();

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
        $client->request('POST', '/product/descriptions/sync', $payload);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($mockedOutput, $responseData['description']);
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItDispatchesAsyncJobAndReturnsAccepted(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
    ): void {
        $client = static::createClient();
        $client->request('POST', '/product/descriptions/async', [
            'name' => $expectedName,
            'features' => $expectedFeatures,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('job_id', $response);
        self::assertSame(GenerateProductDescriptionMessageStatus::PENDING->value, $response['status']);
    }

    public function testItReturns404ForNonExistentJob(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/descriptions/async/non-existent-job-id');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItReturnsJobStatusAndDescriptionWhenReady(): void
    {
        $client = static::createClient();
        $jobStatusManager = static::getContainer()->get(JobStatusManager::class);

        $jobId = 'test-job-999';
        $jobStatusManager->createJob($jobId);
        $jobStatusManager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
            'description' => 'Świetna klawiatura z podświetleniem RGB...',
        ]);

        $url = static::getContainer()->get('router')->generate('app_product_descriptions_async_status', [
            'jobId' => $jobId,
        ]);

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('test-job-999', $response['job_id']);
        self::assertSame(GenerateProductDescriptionMessageStatus::COMPLETED->value, $response['status']);
        self::assertSame('Świetna klawiatura z podświetleniem RGB...', $response['description']);
    }

    public function testItReturns400WhenParametersAreMissingInSync(): void
    {
        $client = static::createClient();

        $syncUrl = static::getContainer()->get('router')->generate('app_product_descriptions_sync');

        $client->request('POST', $syncUrl, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $response);
    }

    public function testItReturns400WhenParametersAreMissingInAsync(): void
    {
        $client = static::createClient();

        $asyncUrl = static::getContainer()->get('router')->generate('app_product_descriptions_async');

        $client->request('POST', $asyncUrl, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $response);
    }
}
