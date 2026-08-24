<?php

declare(strict_types=1);

namespace App\AI\Client;

use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiClient implements AIClientInterface
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    private const API_REVISION = '2026-05-20';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model = 'gemini-3.1-flash-lite-preview',
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {}

    public function generateDescription(DescriptionRequest $descriptionRequest): AIResponse
    {
        $response = $this->httpClient->request('POST', $this->baseUrl, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Api-Revision' => self::API_REVISION,
            ],
            'json' => [
                'model' => $this->model,
                'input' => $descriptionRequest->prompt,
            ],
        ]);
        $data = $response->toArray();

        $description = '';
        foreach ($data['steps'] ?? [] as $step) {
            if (($step['type'] ?? '') === 'model_output') {
                foreach ($step['content'] ?? [] as $item) {
                    $description .= $item['text'] ?? '';
                }
            }
        }

        return new AIResponse(trim($description));
    }
}
