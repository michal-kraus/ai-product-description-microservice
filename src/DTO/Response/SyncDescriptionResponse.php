<?php

declare(strict_types=1);

namespace App\DTO\Response;

use JsonSerializable;

final readonly class SyncDescriptionResponse implements JsonSerializable
{
    public function __construct(
        public string $description,
    ) {}

    /**
     * @return array{description: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
        ];
    }
}
