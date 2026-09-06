<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ProductDescriptionGenerationException;

interface ProductDescriptionGeneratorInterface
{
    /**
     * @throws ProductDescriptionGenerationException
     */
    public function generate(string $productName, string $productFeatures, ?string $requestId = null): string;
}
