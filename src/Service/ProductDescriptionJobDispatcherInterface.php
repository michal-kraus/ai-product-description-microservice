<?php

declare(strict_types=1);

namespace App\Service;

interface ProductDescriptionJobDispatcherInterface
{
    public function dispatch(string $name, string $features, ?string $requestId = null): string;
}
