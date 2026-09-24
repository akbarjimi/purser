<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Enums;

enum ExcelRowStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case FAILED_VALIDATION = 'failed_validation';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::PENDING => in_array($new, [self::VALIDATED, self::FAILED_VALIDATION, self::FAILED]),
            self::VALIDATED => in_array($new, [self::PROCESSED, self::FAILED]),
            self::FAILED_VALIDATION, self::PROCESSED, self::FAILED => false,
        };
    }
}
