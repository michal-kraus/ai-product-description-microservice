<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient implements AIClientInterface
{
    private const GENERATE_ENDPOINT = '/api/generate';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $ollamaUrl = 'http://127.0.0.1:21434',
        private string $model = 'qwen2.5:0.5b',
        private float $temperature = 0.7,
        private bool $stream = false
    ) {}

    public function generateDescription(DescriptionRequest $descriptionRequest): AIResponse
    {
        $json = [
            'model' => $this->model,
            'prompt' => $descriptionRequest->prompt,
            'options' => [
                'temperature' => $this->temperature,
            ],
            'stream' => $this->stream,
        ];

        $url = rtrim($this->ollamaUrl, '/') . self::GENERATE_ENDPOINT;
        $response = $this->httpClient->request('POST', $url, [
            'json' => $json,
        ]);
        $data = $response->toArray();

        return new AIResponse((string) ($data['response'] ?? ''));
    }
}
