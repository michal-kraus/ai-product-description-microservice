<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

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
    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItProcessesMessageAndSavesResult(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
    ): void {
        $generator = $this->createMock(ProductDescriptionGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->with($expectedName, $expectedFeatures)
            ->willReturn($mockedOutput);

        $jobStatusManager = new JobStatusManager(new ArrayAdapter());
        $handler = new GenerateProductDescriptionMessageHandler($generator, new NullLogger(), $jobStatusManager);

        $jobId = 'job-123';
        $message = new GenerateProductDescriptionMessage($jobId, $expectedName, $expectedFeatures);
        $handler($message);

        $savedData = $jobStatusManager->getJob($jobId);
        $this->assertNotNull($savedData);
        $this->assertSame('completed', $savedData['status']);
        $this->assertSame($mockedOutput, $savedData['description']);
    }
}
