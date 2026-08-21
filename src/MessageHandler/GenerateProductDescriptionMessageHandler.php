<?php

namespace App\MessageHandler;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateProductDescriptionMessageHandler
{
    public function __construct(
        private readonly ProductDescriptionGenerator $generator,
        private readonly LoggerInterface $logger,
        private readonly JobStatusManager $jobStatusManager,
    ) {}

    public function __invoke(
        GenerateProductDescriptionMessage $message,
    ): void {
        $this->logger->info('Starting description generation.', [
            'job_id' => $message->jobId,
            'product' => $message->name,
        ]);

        $this->jobStatusManager->updateJob($message->jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);

        try {
            $description = $this->generator->generate($message->name, $message->features);
        } catch (\Throwable $e) {
            $this->logger->error('Description generation failed.', [
                'job_id' => $message->jobId,
                'product' => $message->name,
                'error' => $e->getMessage(),
            ]);

            $this->jobStatusManager->updateJob($message->jobId, [
                'status' => GenerateProductDescriptionMessageStatus::FAILED->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->jobStatusManager->updateJob($message->jobId, [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
            'description' => $description,
        ]);
    }
}
