<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\DescriptionRequest;
use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient implements AIClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $ollamaUrl = 'http://127.0.0.1:21434',
        private string $model = 'qwen2.5:0.5b'
    ) {}
    public function generateDescription(DescriptionRequest $descriptionRequest): AIResponse
    {

        $json = [
            'model' => $this->model,
            'prompt' => $descriptionRequest->prompt,
            'options' => [
                'temperature' => 0.7,
            ],
            'stream' => false,
        ];

        $response = $this->httpClient->request('POST', "{$this->ollamaUrl}/api/generate", [
            'json' => $json,
        ]);
        $data = $response->toArray();
        return new AIResponse((string) ($data['response'] ?? ''));
    }
}
