<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class DescriptionRequest
{
    public function __construct(
        public string $productName,
        public string $productFeatures,
        public string $prompt,
    ) {}
}
