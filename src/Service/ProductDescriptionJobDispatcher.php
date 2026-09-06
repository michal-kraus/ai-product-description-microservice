<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Exception\JobDispatchException;
use App\Message\GenerateProductDescriptionMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

class ProductDescriptionJobDispatcher implements ProductDescriptionJobDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private JobStatusManagerInterface $jobStatusManager,
        private ?LoggerInterface $logger = null,
    ) {}

    public function dispatch(string $name, string $features, ?string $requestId = null): string
    {
        $jobId = Uuid::v7()->toRfc4122();
        $this->jobStatusManager->createJob($jobId);

        try {
            $this->messageBus->dispatch(new GenerateProductDescriptionMessage(
                $jobId,
                $name,
                $features,
                $requestId,
            ));
        } catch (Throwable $e) {
            $this->jobStatusManager->updateJob($jobId, [
                'status' => GenerateProductDescriptionMessageStatus::FAILED->value,
                'error' => 'Unable to dispatch job.',
            ]);

            $this->logger?->error('Failed to dispatch async description job.', [
                'request_id' => $requestId,
                'job_id' => $jobId,
                'product' => $name,
                'exception' => $e,
            ]);

            throw new JobDispatchException(
                'Unable to dispatch job.',
                $jobId,
                $e,
            );
        }

        $this->logger?->info('Async description job dispatched.', [
            'request_id' => $requestId,
            'job_id' => $jobId,
            'product' => $name,
        ]);

        return $jobId;
    }
}
