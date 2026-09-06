<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Client;

use App\AI\Client\GeminiClient;
use App\AI\DTO\DescriptionRequest;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Throwable;

class GeminiClientTest extends TestCase
{
    private const TEST_API_KEY = 'test-api-key';
    private const TEST_MODEL = 'gemini-3.1-flash-lite-preview';

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $mockJson = (string) json_encode([
            'steps' => [
                [
                    'type' => 'model_output',
                    'content' => [
                        ['text' => $mockedOutput],
                    ],
                ],
            ],
        ]);

        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY, self::TEST_MODEL);

        $description = $geminiClient->generateDescription(new DescriptionRequest($expectedName, $expectedFeatures, 'Test prompt'))->description;
        $this->assertSame($mockedOutput, $description);
    }

    public function testItReturnsEmptyDescriptionWhenStepsAreEmpty(): void
    {
        $mockJson = (string) json_encode(['steps' => []]);
        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);
        $description = $geminiClient->generateDescription(new DescriptionRequest('Test', 'Features', 'Test prompt'))->description;

        $this->assertSame('', $description);
    }

    public function testItReturnsEmptyDescriptionWhenNoModelOutputStep(): void
    {
        $mockJson = (string) json_encode([
            'steps' => [
                ['type' => 'user_input', 'content' => [['text' => 'ignored']]],
            ],
        ]);
        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);
        $description = $geminiClient->generateDescription(new DescriptionRequest('Test', 'Features', 'Test prompt'))->description;

        $this->assertSame('', $description);
    }

    public function testItPingsSuccessfully(): void
    {
        $mockResponse = new MockResponse((string) json_encode(['models' => []]), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);

        $this->assertTrue($geminiClient->ping());
    }

    public function testItPingsSuccessfullyWithCustomBaseUrl(): void
    {
        $mockResponse = new MockResponse((string) json_encode(['models' => []]), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY, self::TEST_MODEL, 'https://custom-ai-gateway.example.com/v1beta/models');

        $this->assertTrue($geminiClient->ping());
    }

    public function testItThrowsWhenApiKeyIsEmptyOnPing(): void
    {
        $httpClient = new MockHttpClient();
        $geminiClient = new GeminiClient($httpClient, '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini API key is missing.');

        $geminiClient->ping();
    }

    public function testItThrowsExceptionWhenPingFailsWithNon200(): void
    {
        $mockResponse = new MockResponse('Unauthorized', [
            'http_code' => 401,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini API health check returned status code 401');

        $geminiClient->ping();
    }

    public function testItReturnsConfiguredModel(): void
    {
        $geminiClient = new GeminiClient(new MockHttpClient(), self::TEST_API_KEY, model: 'gemini-custom');
        $this->assertSame('gemini-custom', $geminiClient->getModel());
    }

    public function testItThrowsOnHttpServerError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);

        $this->expectException(Throwable::class);
        $geminiClient->generateDescription(new DescriptionRequest('Test', 'Features', 'Test prompt'));
    }

    public function testItThrowsOnTimeout(): void
    {
        $mockResponse = new MockResponse('', [
            'error' => 'Connection timed out after 60 seconds.',
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);

        $this->expectException(Throwable::class);
        $geminiClient->generateDescription(new DescriptionRequest('Test', 'Features', 'Test prompt'));
    }

    public function testItThrowsOnMalformedJson(): void
    {
        $mockResponse = new MockResponse('{invalid_json_payload', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $geminiClient = new GeminiClient($httpClient, self::TEST_API_KEY);

        $this->expectException(Throwable::class);
        $geminiClient->generateDescription(new DescriptionRequest('Test', 'Features', 'Test prompt'));
    }
}
