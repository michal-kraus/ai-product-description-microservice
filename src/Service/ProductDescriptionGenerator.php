<?php

declare(strict_types=1);

namespace App\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\DescriptionRequest;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProductDescriptionGenerator
{
    public function __construct(
        private AIClientInterface $aiClient,
        private CacheInterface $productDescriptionCache
    ) {}
    public function generate(string $productName, string $productFeatures): string
    {
        $descriptionRequest = new DescriptionRequest($productName, $productFeatures);

        $description = $this->productDescriptionCache->get('product_description_' . md5($productName . '|' . $productFeatures), function (ItemInterface $item) use ($descriptionRequest): string {
            $item->expiresAfter(60);

            $cachedDescription = $this->aiClient->generateDescription($descriptionRequest)->description;
            return $cachedDescription;
        });

        return $description;
    }
}
