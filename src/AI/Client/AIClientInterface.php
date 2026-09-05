<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;

interface AIClientInterface
{
    public function generateDescription(DescriptionRequest $descriptionRequest): AIResponse;

    public function ping(): bool;

    public function getModel(): string;
}
