<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProductDescriptionGenerator;
use PHPUnit\Framework\TestCase;

class ProductDescriptionGeneratorTest extends TestCase
{
    public function testCanProduceDescriptionWithNameAndFeatures(): void
    {
        $generator = new ProductDescriptionGenerator();

        $description = $generator->generate('Test Product', 'Feature 1, Feature 2');
        $this->assertStringContainsString('Test Product', $description);
        $this->assertStringContainsString('Feature 1, Feature 2', $description);
    }
}
