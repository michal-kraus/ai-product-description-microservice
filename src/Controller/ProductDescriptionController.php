<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class ProductDescriptionController extends AbstractController
{
    public const MAX_NAME_LENGTH = 200;
    public const MAX_FEATURES_LENGTH = 2000;

    public function __construct(
        private readonly RateLimiterFactory $productDescriptionApiLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/product/descriptions/sync', name: 'app_product_descriptions_sync', methods: ['POST'])]
    public function generateProductDescription(Request $request, ProductDescriptionGenerator $generator): JsonResponse
    {
        if ($rateLimitResponse = $this->checkRateLimit($request)) {
            return $rateLimitResponse;
        }

        $payload = $request->getPayload();
        $productName = trim($payload->getString('name', $request->request->getString('name')));
        $productFeatures = trim($payload->getString('features', $request->request->getString('features')));

        if ($validationResponse = $this->validateInput($productName, $productFeatures)) {
            return $validationResponse;
        }

        try {
            $description = $generator->generate($productName, $productFeatures);
        } catch (Throwable $e) {
            $this->logger->error('Sync description generation failed.', [
                'product' => $productName,
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
        MessageBusInterface $bus,
        JobStatusManager $jobStatusManager,
    ): JsonResponse {
        if ($rateLimitResponse = $this->checkRateLimit($request)) {
            return $rateLimitResponse;
        }

        $payload = $request->getPayload();
        $productName = trim($payload->getString('name', $request->request->getString('name')));
        $productFeatures = trim($payload->getString('features', $request->request->getString('features')));

        if ($validationResponse = $this->validateInput($productName, $productFeatures)) {
            return $validationResponse;
        }

        $jobId = uniqid();
        $jobStatusManager->createJob($jobId);

        $bus->dispatch(new GenerateProductDescriptionMessage(
            $jobId,
            $productName,
            $productFeatures,
        ));

        $this->logger->info('Async description job dispatched.', [
            'job_id' => $jobId,
            'product' => $productName,
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

    private function validateInput(string $name, string $features): ?JsonResponse
    {
        if ($name === '' || $features === '') {
            return $this->json([
                'error' => 'Missing or empty required parameters: "name" and "features" are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH || mb_strlen($features) > self::MAX_FEATURES_LENGTH) {
            return $this->json([
                'error' => \sprintf(
                    'Input too long. Maximum length: name=%d, features=%d characters.',
                    self::MAX_NAME_LENGTH,
                    self::MAX_FEATURES_LENGTH,
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
