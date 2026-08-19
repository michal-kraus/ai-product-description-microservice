<?php

namespace App\Controller;

use App\Service\ProductDescriptionGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProductDescriptionController extends AbstractController
{
    #[Route('/product/description', name: 'app_product_description')]
    public function generateProductDescription(ProductDescriptionGenerator $generator): JsonResponse
    {
        return $this->json([
            'description' => $generator->generate('Sample Product', 'Feature 1, Feature 2')
        ]);
    }
}
