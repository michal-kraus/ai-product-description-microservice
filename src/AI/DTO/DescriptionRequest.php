<?php

declare(strict_types=1);

namespace App\AI\DTO;

use App\AI\Prompt\PromptBuilder;

readonly class DescriptionRequest
{
    public string $prompt;

    public function __construct(
        public string $productName,
        public string $productFeatures,
        ?string $prompt = null
    ) {
        $this->prompt = $prompt ?? (new PromptBuilder())->build($this->productName, $this->productFeatures);
    }
}
