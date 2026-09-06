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
        $expectedPrompt = $this->promptBuilder->build($expectedName, $expectedFeatures);

        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->method('getModel')->willReturn('test-model');
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->with(new DescriptionRequest($expectedName, $expectedFeatures, $expectedPrompt))
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
        $aiClient->method('getModel')->willReturn('test-model');
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse('Cached description'));

        $generator = $this->createGenerator($aiClient);

        $first = $generator->generate('Product', 'Features');
        $second = $generator->generate('Product', 'Features');

        $this->assertSame('Cached description', $first);
        $this->assertSame($first, $second);
    }

    public function testItInvalidatesCacheWhenProviderOrModelDiffers(): void
    {
        $aiClient1 = $this->createMock(AIClientInterface::class);
        $aiClient1->method('getModel')->willReturn('model-v1');
        $aiClient1->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse('Model 1 description'));

        $generator1 = new ProductDescriptionGenerator(
            $aiClient1,
            $this->cache,
            $this->promptBuilder,
            provider: 'ollama',
        );

        $desc1 = $generator1->generate('Product', 'Features');
        $this->assertSame('Model 1 description', $desc1);

        $aiClient2 = $this->createMock(AIClientInterface::class);
        $aiClient2->method('getModel')->willReturn('model-v2');
        $aiClient2->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse('Model 2 description'));

        $generator2 = new ProductDescriptionGenerator(
            $aiClient2,
            $this->cache,
            $this->promptBuilder,
            provider: 'gemini',
        );

        $desc2 = $generator2->generate('Product', 'Features');
        $this->assertSame('Model 2 description', $desc2);
    }

    public function testItThrowsProductDescriptionGenerationExceptionOnAiFailure(): void
    {
        $aiClient = $this->createStub(AIClientInterface::class);
        $aiClient->method('getModel')->willReturn('test-model');
        $aiClient->method('generateDescription')
            ->willThrowException(new RuntimeException('AI service unavailable'));

        $generator = $this->createGenerator($aiClient);

        $this->expectException(ProductDescriptionGenerationException::class);
        $this->expectExceptionMessage('Failed to generate description for product "FailProduct"');

        $generator->generate('FailProduct', 'Features');
    }

    public function testItTruncatesDescriptionExceedingMaxLength(): void
    {
        $hugeOutput = str_repeat('x', ProductDescriptionGenerator::MAX_DESCRIPTION_LENGTH + 500);

        $aiClient = $this->createMock(AIClientInterface::class);
        $aiClient->method('getModel')->willReturn('test-model');
        $aiClient->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse($hugeOutput));

        $generator = $this->createGenerator($aiClient);

        $description = $generator->generate('Product', 'Features');
        $this->assertSame(ProductDescriptionGenerator::MAX_DESCRIPTION_LENGTH, mb_strlen($description));
    }

    private function createGenerator(AIClientInterface $aiClient, int $ttl = ProductDescriptionGenerator::DEFAULT_CACHE_TTL): ProductDescriptionGenerator
    {
        return new ProductDescriptionGenerator($aiClient, $this->cache, $this->promptBuilder, $ttl);
    }
}
