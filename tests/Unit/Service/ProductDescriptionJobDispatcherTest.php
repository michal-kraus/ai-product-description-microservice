<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Exception\JobDispatchException;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManagerInterface;
use App\Service\ProductDescriptionJobDispatcher;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductDescriptionJobDispatcherTest extends TestCase
{
    public function testItDispatchesSuccessfully(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $jobStatusManager = $this->createMock(JobStatusManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $jobStatusManager->expects($this->once())
            ->method('createJob')
            ->with($this->callback(fn($id) => \is_string($id) && $id !== ''));

        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(GenerateProductDescriptionMessage::class))
            ->willReturnCallback(fn(GenerateProductDescriptionMessage $msg) => new Envelope($msg));

        $logger->expects($this->once())
            ->method('info')
            ->with('Async description job dispatched.', $this->callback(fn($ctx) => \is_array($ctx)));

        $dispatcher = new ProductDescriptionJobDispatcher($bus, $jobStatusManager, $logger);
        $jobId = $dispatcher->dispatch('Product A', 'Feature 1, Feature 2', 'req-123');

        $this->assertNotEmpty($jobId);
    }

    public function testItUpdatesJobAndThrowsWhenDispatchFails(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $jobStatusManager = $this->createMock(JobStatusManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $capturedJobId = null;
        $jobStatusManager->expects($this->once())
            ->method('createJob')
            ->with($this->callback(function (string $id) use (&$capturedJobId): bool {
                $capturedJobId = $id;

                return true;
            }));

        $bus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new Exception('Queue down'));

        $jobStatusManager->expects($this->once())
            ->method('updateJob')
            ->with(
                $this->callback(function (string $id) use (&$capturedJobId): bool {
                    return $id === $capturedJobId;
                }),
                [
                    'status' => GenerateProductDescriptionMessageStatus::FAILED->value,
                    'error' => 'Unable to dispatch job.',
                ],
            );

        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to dispatch async description job.', $this->callback(fn($ctx) => \is_array($ctx)));

        $dispatcher = new ProductDescriptionJobDispatcher($bus, $jobStatusManager, $logger);

        $this->expectException(JobDispatchException::class);
        $this->expectExceptionMessage('Unable to dispatch job.');

        try {
            $dispatcher->dispatch('Product A', 'Feature 1, Feature 2');
        } catch (JobDispatchException $e) {
            $this->assertSame($capturedJobId, $e->getJobId());
            $this->assertSame('Queue down', $e->getPrevious()?->getMessage());

            throw $e;
        }
    }
}
