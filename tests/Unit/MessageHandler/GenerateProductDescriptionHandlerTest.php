<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Exception\ProductDescriptionGenerationException;
use App\Message\GenerateProductDescriptionMessage;
use App\MessageHandler\GenerateProductDescriptionMessageHandler;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class GenerateProductDescriptionHandlerTest extends TestCase
{
    private JobStatusManager $jobStatusManager;

    protected function setUp(): void
    {
        $this->jobStatusManager = new JobStatusManager(new ArrayAdapter());
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItProcessesMessageAndSavesResult(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $generator = $this->createMock(ProductDescriptionGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->with($expectedName, $expectedFeatures)
            ->willReturn($mockedOutput);

        $handler = $this->createHandler($generator);

        $jobId = 'job-123';
        $message = new GenerateProductDescriptionMessage($jobId, $expectedName, $expectedFeatures);
        $handler($message);

        $savedData = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($savedData);
        $this->assertSame('completed', $savedData['status']);
        $this->assertSame($mockedOutput, $savedData['description']);
    }

    public function testItLeavesJobStatusAsProcessingOnGeneratorExceptionToAllowRetry(): void
    {
        $generator = $this->createStub(ProductDescriptionGenerator::class);
        $generator->method('generate')
            ->willThrowException(new ProductDescriptionGenerationException('AI error'));

        $handler = $this->createHandler($generator);

        $jobId = 'job-fail';
        $message = new GenerateProductDescriptionMessage($jobId, 'Product', 'Features');

        $thrownException = null;

        try {
            $handler($message);
        } catch (ProductDescriptionGenerationException $e) {
            $thrownException = $e;
        }

        $this->assertNotNull($thrownException, 'Expected ProductDescriptionGenerationException to be thrown by handler.');
        $this->assertSame('AI error', $thrownException->getMessage());

        $jobData = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($jobData);
        $this->assertSame('processing', $jobData['status']);
    }

    private function createHandler(ProductDescriptionGenerator $generator): GenerateProductDescriptionMessageHandler
    {
        return new GenerateProductDescriptionMessageHandler($generator, new NullLogger(), $this->jobStatusManager);
    }
}
