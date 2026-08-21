<?php

namespace App\Controller;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProductDescriptionController extends AbstractController
{
    #[Route('/product/descriptions/sync', name: 'app_product_descriptions_sync', methods: ['POST'])]
    public function generateProductDescription(Request $request, ProductDescriptionGenerator $generator): JsonResponse
    {
        $payload = $request->getPayload();
        $productName = trim($payload->getString('name', $request->request->getString('name')));
        $productFeatures = trim($payload->getString('features', $request->request->getString('features')));

        if ($productName === '' || $productFeatures === '') {
            return $this->json([
                'error' => 'Missing or empty required parameters: "name" and "features" are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $description = $generator->generate($productName, $productFeatures);
        } catch (\Throwable $e) {
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
        JobStatusManager $jobStatusManager
    ): JsonResponse {
        $payload = $request->getPayload();
        $productName = trim($payload->getString('name', $request->request->getString('name')));
        $productFeatures = trim($payload->getString('features', $request->request->getString('features')));

        if ($productName === '' || $productFeatures === '') {
            return $this->json([
                'error' => 'Missing or empty required parameters: "name" and "features" are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $jobId = uniqid();
        $jobStatusManager->createJob($jobId);

        $bus->dispatch(new GenerateProductDescriptionMessage(
            $jobId,
            $productName,
            $productFeatures
        ));

        return $this->json([
            'job_id' => $jobId,
            'status' => GenerateProductDescriptionMessageStatus::PENDING->value,
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/product/descriptions/async/{jobId}', name: 'app_product_descriptions_async_status', methods: ['GET'])]
    public function getProductDescriptionAsyncStatus(
        string $jobId,
        JobStatusManager $jobStatusManager
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
}
