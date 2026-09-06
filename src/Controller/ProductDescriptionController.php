<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\GenerateProductDescriptionRequest;
use App\DTO\Response\ApiErrorResponse;
use App\DTO\Response\AsyncJobCreatedResponse;
use App\DTO\Response\AsyncJobStatusResponse;
use App\DTO\Response\SyncDescriptionResponse;
use App\Enum\GenerateProductDescriptionMessageStatus;
use App\EventListener\RequestIdListener;
use App\Exception\JobDispatchException;
use App\Service\JobStatusManagerInterface;
use App\Service\ProductDescriptionGenerator;
use App\Service\ProductDescriptionJobDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class ProductDescriptionController extends AbstractController
{
    public const MAX_NAME_LENGTH = GenerateProductDescriptionRequest::MAX_NAME_LENGTH;
    public const MAX_FEATURES_LENGTH = GenerateProductDescriptionRequest::MAX_FEATURES_LENGTH;

    public function __construct(
        private readonly RateLimiterFactory $productDescriptionApiLimiter,
        private readonly RateLimiterFactory $productDescriptionStatusApiLimiter,
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

        $requestId = $this->extractRequestId($request);

        try {
            $description = $generator->generate(
                $productDescriptionRequest->name,
                $productDescriptionRequest->features,
                $requestId,
            );
        } catch (Throwable $e) {
            $this->logger->error('Sync description generation failed.', [
                'request_id' => $requestId,
                'product' => $productDescriptionRequest->name,
                'exception' => $e,
            ]);

            return $this->json(
                new ApiErrorResponse('Failed to generate product description.'),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(new SyncDescriptionResponse($description));
    }

    #[Route('/product/descriptions/async', name: 'app_product_descriptions_async', methods: ['POST'])]
    public function generateProductDescriptionAsync(
        Request $request,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        GenerateProductDescriptionRequest $productDescriptionRequest,
        ProductDescriptionJobDispatcherInterface $jobDispatcher,
    ): JsonResponse {
        if ($rateLimitResponse = $this->checkRateLimit($request)) {
            return $rateLimitResponse;
        }

        $requestId = $this->extractRequestId($request);

        try {
            $jobId = $jobDispatcher->dispatch(
                $productDescriptionRequest->name,
                $productDescriptionRequest->features,
                $requestId,
            );
        } catch (JobDispatchException) {
            return $this->json(
                new ApiErrorResponse('Failed to dispatch async description job.'),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(
            new AsyncJobCreatedResponse($jobId, GenerateProductDescriptionMessageStatus::PENDING->value),
            Response::HTTP_ACCEPTED,
        );
    }

    #[Route('/product/descriptions/async/{jobId}', name: 'app_product_descriptions_async_status', methods: ['GET'])]
    public function getProductDescriptionAsyncStatus(
        string $jobId,
        Request $request,
        JobStatusManagerInterface $jobStatusManager,
    ): JsonResponse {
        if ($rateLimitResponse = $this->checkStatusRateLimit($request)) {
            return $rateLimitResponse;
        }

        $job = $jobStatusManager->getJob($jobId);

        if ($job === null) {
            return $this->json(
                new ApiErrorResponse('Job not found.', jobId: $jobId),
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(new AsyncJobStatusResponse(
            jobId: $jobId,
            status: $job->status->value,
            description: $job->description,
            error: $job->error,
        ));
    }

    private function checkRateLimit(Request $request): ?JsonResponse
    {
        return $this->consumeRateLimit(
            $this->productDescriptionApiLimiter,
            $request,
            'Rate limit exceeded.',
            'Too many requests. Please try again later.',
        );
    }

    private function checkStatusRateLimit(Request $request): ?JsonResponse
    {
        return $this->consumeRateLimit(
            $this->productDescriptionStatusApiLimiter,
            $request,
            'Status polling rate limit exceeded.',
            'Too many status check requests. Please try again later.',
        );
    }

    private function consumeRateLimit(
        RateLimiterFactory $limiterFactory,
        Request $request,
        string $warningLog,
        string $errorMessage,
    ): ?JsonResponse {
        $limiter = $limiterFactory->create($request->getClientIp() ?? 'anonymous');

        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning($warningLog, [
                'request_id' => $this->extractRequestId($request),
                'ip' => $request->getClientIp(),
            ]);

            return $this->json(
                new ApiErrorResponse($errorMessage),
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return null;
    }

    private function extractRequestId(Request $request): ?string
    {
        $requestId = $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);

        return \is_string($requestId) && $requestId !== '' ? $requestId : null;
    }
}
