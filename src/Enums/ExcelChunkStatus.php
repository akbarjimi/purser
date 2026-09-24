<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Enums;

enum ExcelChunkStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::PENDING => in_array($new, [self::PROCESSING, self::FAILED]),
            self::PROCESSING => in_array($new, [self::COMPLETED, self::FAILED]),
            self::FAILED => in_array($new, [self::PENDING]),
            self::COMPLETED => false,
        };
    }
}
