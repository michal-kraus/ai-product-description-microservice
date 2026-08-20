<?php

declare(strict_types=1);

namespace App\Service;

use App\AI\Client\AIClientInterface;

class ProductDescriptionGenerator
{
    public function __construct(private AIClientInterface $aiClient) {}
    public function generate(string $productName, string $productFeatures): string
    {
        return $this->aiClient->generateDescription($productName, $productFeatures);
    }
}
