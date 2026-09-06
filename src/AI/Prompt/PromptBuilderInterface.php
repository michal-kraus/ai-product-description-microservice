<?php

declare(strict_types=1);

namespace App\AI\Prompt;

interface PromptBuilderInterface
{
    public function build(string $productName, string $productFeatures): string;
}
