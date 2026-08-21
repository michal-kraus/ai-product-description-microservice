<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Factory;

use App\AI\Client\GeminiClient;
use App\AI\Client\OllamaClient;
use App\AI\Factory\AIClientFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AIClientFactoryTest extends TestCase
{
    public function testItReturnsOllamaClientWhenProviderIsOllama(): void
    {
        $ollamaClient = $this->createStub(OllamaClient::class);
        $geminiClient = $this->createStub(GeminiClient::class);

        $factory = new AIClientFactory($ollamaClient, $geminiClient, 'ollama');

        $this->assertSame($ollamaClient, $factory->create());
    }

    public function testItReturnsGeminiClientWhenProviderIsGemini(): void
    {
        $ollamaClient = $this->createStub(OllamaClient::class);
        $geminiClient = $this->createStub(GeminiClient::class);

        $factory = new AIClientFactory($ollamaClient, $geminiClient, 'gemini');

        $this->assertSame($geminiClient, $factory->create());
    }

    public function testItThrowsExceptionForUnsupportedProvider(): void
    {
        $ollamaClient = $this->createStub(OllamaClient::class);
        $geminiClient = $this->createStub(GeminiClient::class);

        $factory = new AIClientFactory($ollamaClient, $geminiClient, 'unsupported-provider');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AI provider: "unsupported-provider"');

        $factory->create();
    }
}
