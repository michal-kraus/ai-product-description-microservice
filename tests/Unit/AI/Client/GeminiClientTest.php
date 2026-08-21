<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Client;

use App\AI\Client\GeminiClient;
use App\AI\DTO\DescriptionRequest;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeminiClientTest extends TestCase
{
    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItGeneratesDescription(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
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

        $geminiClient = new GeminiClient($httpClient, 'test-api-key', 'gemini-3.1-flash-lite-preview');

        $description = $geminiClient->generateDescription(new DescriptionRequest($expectedName, $expectedFeatures))->description;
        $this->assertSame($mockedOutput, $description);
    }
}
