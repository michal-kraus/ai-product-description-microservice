<?php

declare(strict_types=1);

namespace App\Enum;

enum GenerateProductDescriptionMessageStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDING => $target === self::PROCESSING || $target === self::FAILED,
            self::PROCESSING => $target === self::COMPLETED || $target === self::FAILED || $target === self::PROCESSING,
            self::COMPLETED, self::FAILED => false,
        };
    }
}
