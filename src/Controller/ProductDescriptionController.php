<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\GenerateProductDescriptionRequest;
use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class ProductDescriptionController extends AbstractController
{
    public const MAX_NAME_LENGTH = GenerateProductDescriptionRequest::MAX_NAME_LENGTH;
    public const MAX_FEATURES_LENGTH = GenerateProductDescriptionRequest::MAX_FEATURES_LENGTH;

    public function __construct(
        private readonly RateLimiterFactory $productDescriptionApiLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/product/descriptions/sync', name: 'app_product_descriptions_sync', methods: ['POST'])]
    public function generateProductDescription(
        Request $request,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        GenerateProductDescriptionRequest $productDescriptionRequest,
        ProductDescriptionGenerator $generator,
    ): JsonResponse {
        if ($rateLimitResponse = $this->checkRateLimit($request)) {
            return $rateLimitResponse;
        }

        try {
            $description = $generator->generate(
                $productDescriptionRequest->name,
                $productDescriptionRequest->features,
            );
        } catch (Throwable $e) {
            $this->logger->error('Sync description generation failed.', [
                'product' => $productDescriptionRequest->name,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Failed to generate product description.',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'description' => $description,
        ]);
    }

    #[Route('/product/descriptions/async', name: 'app_product_descriptions_async', methods: ['POST'])]
    public function generateProductDescriptionAsync(
        Request $request,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        GenerateProductDescriptionRequest $productDescriptionRequest,
        MessageBusInterface $bus,
        JobStatusManager $jobStatusManager,
    ): JsonResponse {
        if ($rateLimitResponse = $this->checkRateLimit($request)) {
            return $rateLimitResponse;
        }

        $jobId = uniqid();
        $jobStatusManager->createJob($jobId);

        $bus->dispatch(new GenerateProductDescriptionMessage(
            $jobId,
            $productDescriptionRequest->name,
            $productDescriptionRequest->features,
        ));

        $this->logger->info('Async description job dispatched.', [
            'job_id' => $jobId,
            'product' => $productDescriptionRequest->name,
        ]);

        return $this->json([
            'job_id' => $jobId,
            'status' => GenerateProductDescriptionMessageStatus::PENDING->value,
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/product/descriptions/async/{jobId}', name: 'app_product_descriptions_async_status', methods: ['GET'])]
    public function getProductDescriptionAsyncStatus(
        string $jobId,
        JobStatusManager $jobStatusManager,
    ): JsonResponse {
        $jobData = $jobStatusManager->getJob($jobId);

        if ($jobData === null) {
            return $this->json([
                'error' => 'Job not found.',
                'job_id' => $jobId,
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'job_id' => $jobId,
            'status' => $jobData['status'],
            'description' => $jobData['description'] ?? null,
            'error' => $jobData['error'] ?? null,
        ]);
    }

    private function checkRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->productDescriptionApiLimiter->create($request->getClientIp() ?? 'anonymous');

        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning('Rate limit exceeded.', [
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'error' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return null;
    }
}
