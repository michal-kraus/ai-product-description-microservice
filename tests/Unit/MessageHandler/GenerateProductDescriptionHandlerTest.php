<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\EventListener\JobFailedListener;
use App\Exception\ProductDescriptionGenerationException;
use App\Message\GenerateProductDescriptionMessage;
use App\MessageHandler\GenerateProductDescriptionMessageHandler;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class GenerateProductDescriptionHandlerTest extends TestCase
{
    private JobStatusManager $jobStatusManager;

    protected function setUp(): void
    {
        $this->jobStatusManager = new JobStatusManager(new ArrayAdapter());
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItProcessesMessageFromPendingToCompleted(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $jobId = 'job-123';
        $requestId = 'req-abc-456';

        $this->jobStatusManager->createJob($jobId);
        $initialJob = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($initialJob);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PENDING->value, $initialJob['status']);

        $generator = $this->createMock(ProductDescriptionGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->with($expectedName, $expectedFeatures, $requestId)
            ->willReturn($mockedOutput);

        $handler = $this->createHandler($generator);

        $message = new GenerateProductDescriptionMessage($jobId, $expectedName, $expectedFeatures, $requestId);
        $handler($message);

        $savedData = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($savedData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::COMPLETED->value, $savedData['status']);
        $this->assertSame($mockedOutput, $savedData['description']);
    }

    public function testItLeavesJobStatusAsProcessingOnGeneratorExceptionToAllowRetry(): void
    {
        $jobId = 'job-fail';
        $this->jobStatusManager->createJob($jobId);

        $generator = $this->createStub(ProductDescriptionGenerator::class);
        $generator->method('generate')
            ->willThrowException(new ProductDescriptionGenerationException('AI error'));

        $handler = $this->createHandler($generator);

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
        $this->assertSame(GenerateProductDescriptionMessageStatus::PROCESSING->value, $jobData['status']);
    }

    public function testFullLifecycleFromPendingToRetryAndPermanentFailure(): void
    {
        $jobId = 'job-lifecycle';
        $this->jobStatusManager->createJob($jobId);

        $generator = $this->createStub(ProductDescriptionGenerator::class);
        $generator->method('generate')
            ->willThrowException(new ProductDescriptionGenerationException('AI connection timed out'));

        $handler = $this->createHandler($generator);
        $failedListener = new JobFailedListener($this->jobStatusManager, new NullLogger());

        $message = new GenerateProductDescriptionMessage($jobId, 'Phone', 'Fast CPU', 'req-lifecycle-1');

        try {
            $handler($message);
            $this->fail('Expected exception from handler');
        } catch (ProductDescriptionGenerationException) {
        }

        $jobAttempt1 = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($jobAttempt1);
        $this->assertSame(
            GenerateProductDescriptionMessageStatus::PROCESSING->value,
            $jobAttempt1['status'],
        );

        $envelope = new Envelope($message);
        $retryEvent = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('AI connection timed out'));
        $retryEvent->setForRetry();
        $this->assertTrue($retryEvent->willRetry());

        $failedListener($retryEvent);

        $jobRetry = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($jobRetry);
        $this->assertSame(
            GenerateProductDescriptionMessageStatus::PROCESSING->value,
            $jobRetry['status'],
        );

        $terminalEvent = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('AI connection timed out'));
        $this->assertFalse($terminalEvent->willRetry());

        $failedListener($terminalEvent);

        $finalJob = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($finalJob);
        $this->assertSame(GenerateProductDescriptionMessageStatus::FAILED->value, $finalJob['status']);
        $this->assertSame('Job execution failed after all retry attempts.', $finalJob['error']);
    }

    private function createHandler(ProductDescriptionGenerator $generator): GenerateProductDescriptionMessageHandler
    {
        return new GenerateProductDescriptionMessageHandler($generator, new NullLogger(), $this->jobStatusManager);
    }
}
