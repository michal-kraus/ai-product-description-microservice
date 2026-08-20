<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\AIResponse;

interface AIClientInterface
{
    public function generateDescription(string $productName, string $productFeatures): AIResponse;
}
