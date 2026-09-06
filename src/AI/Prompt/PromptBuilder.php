<?php

declare(strict_types=1);

namespace App\AI\Prompt;

final readonly class PromptBuilder implements PromptBuilderInterface
{
    public const DEFAULT_TEMPLATE = "Jesteś copywriterem e-commerce. Napisz krótki i atrakcyjny opis produktu po polsku:\nNazwa: {name}\nCechy: {features}";

    public function __construct(
        private string $template = self::DEFAULT_TEMPLATE,
    ) {}

    public function build(string $productName, string $productFeatures): string
    {
        return str_replace(
            ['{name}', '{features}'],
            [$productName, $productFeatures],
            $this->template,
        );
    }
}
