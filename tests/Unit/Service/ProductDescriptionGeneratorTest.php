<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use App\Service\ProductDescriptionGenerator;

use PHPUnit\Framework\TestCase;

class ProductDescriptionGeneratorTest extends TestCase
{
    public function testItProducesDescriptionWithGivenNameAndFeatures(): void
    {
        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->with('Test Product', 'Feature 1, Feature 2')
            ->willReturn(new AIResponse('Introducing our latest product: Test Product! It comes with amazing features such as Feature 1, Feature 2. Get yours today!'));

        $generator = new ProductDescriptionGenerator($aiClient);

        $description = $generator->generate('Test Product', 'Feature 1, Feature 2');
        $this->assertStringContainsString('Test Product', $description);
        $this->assertStringContainsString('Feature 1, Feature 2', $description);
    }
}
