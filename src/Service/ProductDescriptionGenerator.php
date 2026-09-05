<?php

declare(strict_types=1);

namespace App\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\DescriptionRequest;
use App\AI\Prompt\PromptBuilder;
use App\Exception\ProductDescriptionGenerationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class ProductDescriptionGenerator
{
    public const CACHE_KEY_PREFIX = 'product_description_';
    public const CACHE_VERSION = 'v1';
    public const DEFAULT_CACHE_TTL = 600;

    public function __construct(
        private AIClientInterface $aiClient,
        private CacheInterface $productDescriptionCache,
        private PromptBuilder $promptBuilder = new PromptBuilder(),
        private int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private ?LoggerInterface $logger = null,
        private string $provider = 'default',
        private string $model = 'default',
    ) {}

    public function generate(string $productName, string $productFeatures): string
    {
        $prompt = $this->promptBuilder->build($productName, $productFeatures);
        $descriptionRequest = new DescriptionRequest($productName, $productFeatures, $prompt);

        $cacheKey = $this->buildCacheKey($productName, $productFeatures, $prompt);

        try {
            $cacheHit = true;
            $description = $this->productDescriptionCache->get($cacheKey, function (ItemInterface $item) use ($descriptionRequest, $productName, &$cacheHit): string {
                $cacheHit = false;
                $item->expiresAfter($this->cacheTtl);

                $this->logger?->info('Generating description via AI client.', [
                    'product' => $productName,
                ]);

                return $this->aiClient->generateDescription($descriptionRequest)->description;
            });

            if ($cacheHit) {
                $this->logger?->debug('Description served from cache.', [
                    'product' => $productName,
                    'cache_key' => $cacheKey,
                ]);
            }
        } catch (Throwable $e) {
            $this->logger?->error('Failed to generate product description.', [
                'product' => $productName,
                'error' => $e->getMessage(),
            ]);

            throw new ProductDescriptionGenerationException(
                \sprintf('Failed to generate description for product "%s".', $productName),
                previous: $e,
            );
        }

        return $description;
    }

    private function buildCacheKey(string $productName, string $productFeatures, string $prompt): string
    {
        $activeModel = $this->aiClient->getModel();
        $model = ($activeModel !== '') ? $activeModel : $this->model;

        $hash = hash('xxh128', implode('|', [
            self::CACHE_VERSION,
            $this->provider,
            $model,
            $productName,
            $productFeatures,
            $prompt,
        ]));

        return self::CACHE_KEY_PREFIX . $hash;
    }
}
