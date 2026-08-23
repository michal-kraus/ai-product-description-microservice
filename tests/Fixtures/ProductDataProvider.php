<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

final class ProductDataProvider
{
    /**
     * @return iterable<string, array{expectedName: string, expectedFeatures: string, mockedOutput: string}>
     */
    public static function providePayloads(): iterable
    {
        yield 'with custom parameters' => [
            'expectedName' => 'Laptop Pro',
            'expectedFeatures' => '16GB RAM, SSD 1TB',
            'mockedOutput' => 'Opis dla Laptop Pro z 16GB RAM, SSD 1TB',
        ];

        yield 'with default parameters' => [
            'expectedName' => 'Sample Product',
            'expectedFeatures' => 'Feature 1, Feature 2',
            'mockedOutput' => 'Opis dla Sample Product z Feature 1, Feature 2',
        ];

        yield 'with special characters' => [
            'expectedName' => 'Smartfon & Akcesoria',
            'expectedFeatures' => 'Ekran 6.7", Bateria 5000mAh',
            'mockedOutput' => 'Opis dla Smartfon & Akcesoria z Ekran 6.7", Bateria 5000mAh',
        ];
    }
}
