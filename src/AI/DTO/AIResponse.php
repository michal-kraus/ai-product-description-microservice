<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class AIResponse
{
    public function __construct(private string $description) {}

    public function getDescription(): string
    {
        return $this->description;
    }
}
