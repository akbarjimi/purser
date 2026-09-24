<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Enums;

enum ExcelSheetStatus: string
{
    case PENDING = 'pending';
    case EXTRACTING = 'extracting';
    case EXTRACTED = 'extracted';
    case CHUNKS_DISPATCHED = 'chunks_dispatched';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::PENDING => in_array($new, [self::EXTRACTING, self::FAILED]),
            self::EXTRACTING => in_array($new, [self::EXTRACTED, self::FAILED]),
            self::EXTRACTED => in_array($new, [self::CHUNKS_DISPATCHED, self::EXTRACTING, self::FAILED]),
            self::CHUNKS_DISPATCHED => in_array($new, [self::COMPLETED, self::FAILED]),
            self::COMPLETED, self::FAILED => false,
        };
    }
}
