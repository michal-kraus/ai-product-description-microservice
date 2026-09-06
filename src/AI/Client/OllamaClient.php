<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaClient implements AIClientInterface
{
    private const GENERATE_ENDPOINT = '/api/generate';
    private const VERSION_ENDPOINT = '/api/version';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $ollamaUrl = 'http://127.0.0.1:21434',
        private string $model = 'qwen2.5:0.5b',
        private float $temperature = 0.7,
        private bool $stream = false,
        private float $timeout = 60.0,
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
            'timeout' => $this->timeout,
        ]);
        $data = $response->toArray();

        return new AIResponse((string) ($data['response'] ?? ''));
    }

    public function ping(): bool
    {
        $url = rtrim($this->ollamaUrl, '/') . self::VERSION_ENDPOINT;
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 3.0,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(\sprintf('Ollama health check returned status code %d.', $response->getStatusCode()));
        }

        return true;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
