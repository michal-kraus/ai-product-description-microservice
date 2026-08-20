<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Client;

use App\AI\Client\AIClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AIClientTest extends TestCase
{
    public function testItGeneratesDescription(): void
    {
        $mockJson = json_encode([
            'model' => 'qwen2.5:0.5b',
            'response' => 'This is a Test Product description with features Feature 1, Feature 2.',
            'done' => true,
        ]);

        $mockResponse = new MockResponse($mockJson, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $aiClient = new AIClient($httpClient, 'http://127.0.0.1:21434');

        $description = $aiClient->generateDescription('Test Product', 'Feature 1, Feature 2')->getDescription();
        $this->assertSame('This is a Test Product description with features Feature 1, Feature 2.', $description);
    }
}
