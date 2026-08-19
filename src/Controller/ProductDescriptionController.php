<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use App\Service\ProductDescriptionGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProductDescriptionController extends AbstractController
{
    #[Route('/product/description', name: 'app_product_description', methods: ['POST'])]
    public function generateProductDescription(Request $request, ProductDescriptionGenerator $generator): JsonResponse
    {
        $productName = (string) $request->request->get('name', 'Sample Product');
        $productFeatures = (string) $request->request->get('features', 'Feature 1, Feature 2');

        return $this->json([
            'description' => $generator->generate($productName, $productFeatures)
        ]);
    }
}
