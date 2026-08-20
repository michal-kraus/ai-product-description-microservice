<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductDescriptionControllerTest extends WebTestCase
{
    #[DataProvider('providePayloads')]
    public function testItGeneratesDescription(
        array $payload,
        string $expectedName,
        string $expectedFeatures,
        string $mockedOutput
    ): void {
        $client = static::createClient();

        $aiClientMock = $this->createMock(AIClientInterface::class);
        $aiClientMock->expects($this->once())
            ->method('generateDescription')
            ->with($expectedName, $expectedFeatures)
            ->willReturn(new AIResponse($mockedOutput));

        static::getContainer()->set(AIClientInterface::class, $aiClientMock);

        $client->request('POST', '/product/description', $payload);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($mockedOutput, $responseData['description']);
    }

    public static function providePayloads(): iterable
    {
        yield 'with custom parameters' => [
            'payload' => ['name' => 'Laptop Pro', 'features' => '16GB RAM, SSD 1TB'],
            'expectedName' => 'Laptop Pro',
            'expectedFeatures' => '16GB RAM, SSD 1TB',
            'mockedOutput' => 'Opis dla Laptop Pro z 16GB RAM, SSD 1TB',
        ];

        yield 'with default parameters' => [
            'payload' => [],
            'expectedName' => 'Sample Product',
            'expectedFeatures' => 'Feature 1, Feature 2',
            'mockedOutput' => 'Opis dla Sample Product z Feature 1, Feature 2',
        ];
    }
}
