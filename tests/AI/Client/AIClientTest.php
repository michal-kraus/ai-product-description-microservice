<?php

namespace App\Tests\AI\Client;

use App\AI\Client\AIClient;
use PHPUnit\Framework\TestCase;

class AIClientTest extends TestCase
{
    public function testItGeneratesDescription(): void
    {
        $aiClient = new AIClient();

        $description = $aiClient->generateDescription('Test Product', 'Feature 1, Feature 2');
        $this->assertStringContainsString('Test Product', $description);
        $this->assertStringContainsString('Feature 1, Feature 2', $description);
    }
}
