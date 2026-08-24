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
    private OllamaClient $ollamaClient;
    private GeminiClient $geminiClient;

    protected function setUp(): void
    {
        $this->ollamaClient = $this->createStub(OllamaClient::class);
        $this->geminiClient = $this->createStub(GeminiClient::class);
    }

    public function testItReturnsOllamaClientWhenProviderIsOllama(): void
    {
        $factory = $this->createFactory('ollama');

        $this->assertSame($this->ollamaClient, $factory->create());
    }

    public function testItReturnsGeminiClientWhenProviderIsGemini(): void
    {
        $factory = $this->createFactory('gemini');

        $this->assertSame($this->geminiClient, $factory->create());
    }

    public function testItThrowsExceptionForUnsupportedProvider(): void
    {
        $factory = $this->createFactory('unsupported-provider');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AI provider: "unsupported-provider"');

        $factory->create();
    }

    private function createFactory(string $provider): AIClientFactory
    {
        return new AIClientFactory($this->ollamaClient, $this->geminiClient, $provider);
    }
}
