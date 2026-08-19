<?php

declare(strict_types=1);

namespace App\Service;

class ProductDescriptionGenerator
{
    public function generate(string $productName, string $productFeatures): string
    {
        //TODO: Implement the logic to generate a product description based on the product name and features.
        return "Introducing our latest product: $productName! It comes with amazing features such as $productFeatures. Get yours today!";
    }
}
