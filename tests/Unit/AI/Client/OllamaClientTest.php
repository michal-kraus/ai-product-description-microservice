<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Client;

use App\AI\Client\OllamaClient;
use App\AI\DTO\DescriptionRequest;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Throwable;

class OllamaClientTest extends TestCase
{
    private const TEST_OLLAMA_URL = 'http://127.0.0.1:21434';

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $mockJson = (string) json_encode([
            'model' => 'qwen2.5:0.5b',
            'response' => $mockedOutput,
            'done' => true,
        ]);

        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $ollamaClient = new OllamaClient($httpClient, self::TEST_OLLAMA_URL);

        $description = $ollamaClient->generateDescription(new DescriptionRequest($expectedName, $expectedFeatures))->description;
        $this->assertSame($mockedOutput, $description);
    }

    public function testItReturnsEmptyDescriptionWhenResponseFieldIsMissing(): void
    {
        $mockJson = (string) json_encode(['model' => 'qwen2.5:0.5b', 'done' => true]);
        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $ollamaClient = new OllamaClient($httpClient, self::TEST_OLLAMA_URL);
        $description = $ollamaClient->generateDescription(new DescriptionRequest('Test', 'Features'))->description;

        $this->assertSame('', $description);
    }

    public function testItThrowsOnHttpServerError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $ollamaClient = new OllamaClient($httpClient, self::TEST_OLLAMA_URL);

        $this->expectException(Throwable::class);
        $ollamaClient->generateDescription(new DescriptionRequest('Test', 'Features'));
    }

    public function testItPingsSuccessfully(): void
    {
        $mockResponse = new MockResponse((string) json_encode(['version' => '0.5.1']), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $ollamaClient = new OllamaClient($httpClient, self::TEST_OLLAMA_URL);

        $this->assertTrue($ollamaClient->ping());
    }

    public function testItThrowsExceptionWhenPingFailsWithNon200(): void
    {
        $mockResponse = new MockResponse('Service Unavailable', [
            'http_code' => 503,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $ollamaClient = new OllamaClient($httpClient, self::TEST_OLLAMA_URL);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Ollama health check returned status code 503');

        $ollamaClient->ping();
    }
}
