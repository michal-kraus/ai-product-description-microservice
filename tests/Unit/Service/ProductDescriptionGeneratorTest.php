<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use App\AI\Prompt\PromptBuilder;
use App\Service\ProductDescriptionGenerator;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ProductDescriptionGeneratorTest extends TestCase
{
    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItProducesDescriptionWithGivenNameAndFeatures(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
    ): void {
        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->with(new DescriptionRequest($expectedName, $expectedFeatures))
            ->willReturn(new AIResponse($mockedOutput));

        $generator = new ProductDescriptionGenerator($aiClient, new ArrayAdapter(), new PromptBuilder());

        $description = $generator->generate($expectedName, $expectedFeatures);
        $this->assertStringContainsString($expectedName, $description);
        $this->assertStringContainsString($expectedFeatures, $description);
        $this->assertSame($mockedOutput, $description);
    }
}
