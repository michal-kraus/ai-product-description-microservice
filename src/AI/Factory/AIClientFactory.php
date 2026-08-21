<?php

declare(strict_types=1);

namespace App\AI\Factory;

use App\AI\Client\AIClientInterface;
use App\AI\Client\GeminiClient;
use App\AI\Client\OllamaClient;
use InvalidArgumentException;

class AIClientFactory
{
    public function __construct(
        private OllamaClient $ollamaClient,
        private GeminiClient $geminiClient,
        private string $provider = 'ollama'
    ) {}

    public function create(): AIClientInterface
    {
        return match (strtolower($this->provider)) {
            'gemini' => $this->geminiClient,
            'ollama' => $this->ollamaClient,
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported AI provider: "%s". Supported providers are "ollama", "gemini".',
                $this->provider
            )),
        };
    }
}
