<?php

declare(strict_types=1);

namespace App\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\DescriptionRequest;

class ProductDescriptionGenerator
{
    public function __construct(
        private AIClientInterface $aiClient
    ) {}
    public function generate(string $productName, string $productFeatures): string
    {
        $descriptionRequest = new DescriptionRequest($productName, $productFeatures);
        $description = $this->aiClient->generateDescription($descriptionRequest)->description;
        return $description;
    }
}
