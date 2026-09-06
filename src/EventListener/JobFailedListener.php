<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
final readonly class JobFailedListener
{
    public function __construct(
        private JobStatusManagerInterface $jobStatusManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof GenerateProductDescriptionMessage) {
            return;
        }

        if ($event->willRetry()) {
            $this->logger->warning('Async description job failed, retry scheduled by Messenger.', [
                'request_id' => $message->requestId,
                'job_id' => $message->jobId,
                'product' => $message->name,
                'error' => $event->getThrowable()->getMessage(),
            ]);

            return;
        }

        $this->logger->error('Async description job failed permanently after retries exhausted.', [
            'request_id' => $message->requestId,
            'job_id' => $message->jobId,
            'product' => $message->name,
            'error' => $event->getThrowable()->getMessage(),
        ]);

        $this->jobStatusManager->updateJob($message->jobId, [
            'status' => GenerateProductDescriptionMessageStatus::FAILED->value,
            'error' => 'Job execution failed after all retry attempts.',
        ]);
    }
}
