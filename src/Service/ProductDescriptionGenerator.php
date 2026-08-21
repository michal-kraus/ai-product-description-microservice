<?php

declare(strict_types=1);


namespace App\Service;

use App\Exception\ProductDescriptionGenerationException;
use App\AI\Client\AIClientInterface;
use App\AI\DTO\DescriptionRequest;
use App\AI\Prompt\PromptBuilder;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProductDescriptionGenerator
{
    public const CACHE_KEY_PREFIX = 'product_description_';
    public const DEFAULT_CACHE_TTL = 600;

    public function __construct(
        private AIClientInterface $aiClient,
        private CacheInterface $productDescriptionCache,
        private PromptBuilder $promptBuilder = new PromptBuilder(),
        private int $cacheTtl = self::DEFAULT_CACHE_TTL
    ) {}

    public function generate(string $productName, string $productFeatures): string
    {
        $prompt = $this->promptBuilder->build($productName, $productFeatures);
        $descriptionRequest = new DescriptionRequest($productName, $productFeatures, $prompt);

        $cacheKey = $this->buildCacheKey($productName, $productFeatures);

        $description = $this->productDescriptionCache->get($cacheKey, function (ItemInterface $item) use ($descriptionRequest): string {
            $item->expiresAfter($this->cacheTtl);

            $cachedDescription = $this->aiClient->generateDescription($descriptionRequest)->description;
            return $cachedDescription;
        });

        return $description;
    }

    private function buildCacheKey(string $productName, string $productFeatures): string
    {
        return self::CACHE_KEY_PREFIX . md5($productName . '|' . $productFeatures);
    }
}
