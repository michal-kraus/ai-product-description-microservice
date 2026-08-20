<?php

declare(strict_types=1);

namespace App\AI\Client;

interface AIClientInterface
{
    public function generateDescription(string $productName, string $productFeatures): string;
}
