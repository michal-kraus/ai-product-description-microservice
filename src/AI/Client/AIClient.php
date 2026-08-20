<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIRequest;
use App\AI\DTO\AIResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIClient implements AIClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $ollamaUrl = 'http://127.0.0.1:21434'
    ) {}
    public function generateDescription(string $productName, string $productFeatures): AIResponse
    {
        $AIRequest = new AIRequest('qwen2.5:0.5b', $productName, $productFeatures);
        $json = $AIRequest->createRequest();

        $response = $this->httpClient->request('POST', "{$this->ollamaUrl}/api/generate", [
            'json' => $json,
        ]);
        $data = $response->toArray();
        return new AIResponse((string) ($data['response'] ?? ''));
    }
}
