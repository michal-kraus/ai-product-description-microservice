<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\EventListener\JobFailedListener;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class JobFailedListenerTest extends TestCase
{
    private JobStatusManager $jobStatusManager;
    private JobFailedListener $listener;

    protected function setUp(): void
    {
        $this->jobStatusManager = new JobStatusManager(new ArrayAdapter(), new LockFactory(new InMemoryStore()));
        $this->listener = new JobFailedListener($this->jobStatusManager, new NullLogger());
    }

    public function testItDoesNothingForUnrelatedMessages(): void
    {
        $envelope = new Envelope(new stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('Error'));

        ($this->listener)($event);

        $this->assertNull($this->jobStatusManager->getJob('any-job-id'));
    }

    public function testItKeepsStatusWhenRetryIsScheduled(): void
    {
        $jobId = 'job-retry';
        $this->jobStatusManager->createJob($jobId);
        $this->jobStatusManager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);

        $message = new GenerateProductDescriptionMessage($jobId, 'Product', 'Features');
        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('Transient failure'));
        $event->setForRetry();

        $this->assertTrue($event->willRetry());

        ($this->listener)($event);

        $jobData = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($jobData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PROCESSING->value, $jobData['status']);
    }

    public function testItMarksJobAsFailedWhenRetriesAreExhausted(): void
    {
        $jobId = 'job-failed-permanently';
        $this->jobStatusManager->createJob($jobId);
        $this->jobStatusManager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);

        $message = new GenerateProductDescriptionMessage($jobId, 'Product', 'Features');
        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('Permanent failure'));

        $this->assertFalse($event->willRetry());

        ($this->listener)($event);

        $jobData = $this->jobStatusManager->getJob($jobId);
        $this->assertNotNull($jobData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::FAILED->value, $jobData['status']);
        $this->assertSame('Job execution failed after all retry attempts.', $jobData['error']);
    }
}
