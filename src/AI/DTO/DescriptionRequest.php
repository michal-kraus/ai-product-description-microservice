<?php

declare(strict_types=1);

namespace App\AI\DTO;

readonly class DescriptionRequest
{
    public string $prompt;
    public function __construct(private string $productName, private string $productFeatures)
    {
        $this->prompt = $this->prompt();
    }

    private function prompt(): string
    {
        return <<<PROMPT
            Jesteś copywriterem e-commerce. Napisz krótki i atrakcyjny opis produktu po polsku:
            Nazwa: {$this->productName}
            Cechy: {$this->productFeatures}
        PROMPT;
    }
}
