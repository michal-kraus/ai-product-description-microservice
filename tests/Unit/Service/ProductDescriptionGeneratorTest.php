<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use App\AI\DTO\DescriptionRequest;
use App\AI\Prompt\PromptBuilder;
use App\Exception\ProductDescriptionGenerationException;
use App\Service\ProductDescriptionGenerator;
use App\Tests\Fixtures\ProductDataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ProductDescriptionGeneratorTest extends TestCase
{
    private ArrayAdapter $cache;
    private PromptBuilder $promptBuilder;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->promptBuilder = new PromptBuilder();
    }

    #[DataProviderExternal(ProductDataProvider::class, 'providePayloads')]
    public function testItProducesDescriptionWithGivenNameAndFeatures(
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput,
    ): void {
        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->with(new DescriptionRequest($expectedName, $expectedFeatures))
            ->willReturn(new AIResponse($mockedOutput));

        $generator = $this->createGenerator($aiClient);

        $description = $generator->generate($expectedName, $expectedFeatures);
        $this->assertStringContainsString($expectedName, $description);
        $this->assertStringContainsString($expectedFeatures, $description);
        $this->assertSame($mockedOutput, $description);
    }

    public function testItReturnsCachedDescriptionWithoutCallingAiClientTwice(): void
    {
        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse('Cached description'));

        $generator = $this->createGenerator($aiClient);

        $first = $generator->generate('Product', 'Features');
        $second = $generator->generate('Product', 'Features');

        $this->assertSame('Cached description', $first);
        $this->assertSame($first, $second);
    }

    public function testItThrowsProductDescriptionGenerationExceptionOnAiFailure(): void
    {
        $aiClient = $this->createStub(AIClientInterface::class);
        $aiClient->method('generateDescription')
            ->willThrowException(new RuntimeException('AI service unavailable'));

        $generator = $this->createGenerator($aiClient);

        $this->expectException(ProductDescriptionGenerationException::class);
        $this->expectExceptionMessage('Failed to generate description for product "FailProduct"');

        $generator->generate('FailProduct', 'Features');
    }

    private function createGenerator(AIClientInterface $aiClient, int $ttl = ProductDescriptionGenerator::DEFAULT_CACHE_TTL): ProductDescriptionGenerator
    {
        return new ProductDescriptionGenerator($aiClient, $this->cache, $this->promptBuilder, $ttl);
    }
}
