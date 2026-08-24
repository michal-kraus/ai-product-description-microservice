<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Prompt;

use App\AI\Prompt\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    public function testItBuildsPromptWithDefaultTemplate(): void
    {
        $builder = new PromptBuilder();
        $prompt = $builder->build('Test Product', 'Feature A, Feature B');

        $this->assertStringContainsString('Test Product', $prompt);
        $this->assertStringContainsString('Feature A, Feature B', $prompt);
    }

    public function testItBuildsPromptWithCustomTemplate(): void
    {
        $template = 'Custom description for {name} with features: {features}';
        $builder = new PromptBuilder($template);
        $prompt = $builder->build('Laptop', '16GB RAM');

        $this->assertSame('Custom description for Laptop with features: 16GB RAM', $prompt);
    }
}
