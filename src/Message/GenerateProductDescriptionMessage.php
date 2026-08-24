<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GenerateProductDescriptionMessage
{
    public function __construct(
        public string $jobId,
        public string $name,
        public string $features,
    ) {}
}
