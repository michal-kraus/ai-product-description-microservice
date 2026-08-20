<?php

declare(strict_types=1);

namespace App\AI\DTO;

class AIRequest
{
    public function __construct(private string $model, private string $productName, private string $productFeatures) {}
    public function createRequest(): array
    {
        return [
            'model' => $this->model,
            'prompt' => "Jesteś copywriterem e-commerce. Napisz krótki i atrakcyjny opis produktu po polsku:\\nNazwa: {$this->productName}\\nCechy: {$this->productFeatures}",
            'options' => [
                'temperature' => 0.7,
            ],
            'stream' => false,
        ];
    }
}
