<?php

declare(strict_types=1);

namespace App\Enum;

enum GenerateProductDescriptionMessageStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
