<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductDescriptionControllerTest extends WebTestCase
{
    public function testItGeneratesDescriptionWithProvidedParameters(): void
    {
        $client = static::createClient();
        $client->request('POST', '/product/description', [
            'name' => 'Laptop Pro',
            'features' => '16GB RAM, SSD 1TB',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertIsArray($responseData);
        self::assertArrayHasKey('description', $responseData);
        self::assertStringContainsString('Laptop Pro', $responseData['description']);
        self::assertStringContainsString('16GB RAM, SSD 1TB', $responseData['description']);
    }

    public function testItGeneratesDescriptionWithDefaultParametersWhenNoneProvided(): void
    {
        $client = static::createClient();
        $client->request('POST', '/product/description');

        self::assertResponseIsSuccessful();

        $responseData = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertArrayHasKey('description', $responseData);
        self::assertStringContainsString('Sample Product', $responseData['description']);
        self::assertStringContainsString('Feature 1, Feature 2', $responseData['description']);
    }
}
